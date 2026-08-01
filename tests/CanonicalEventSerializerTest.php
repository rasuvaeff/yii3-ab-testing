<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\CanonicalEventSerializer;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(CanonicalEventSerializer::class)]
final class CanonicalEventSerializerTest
{
    private CanonicalEventSerializer $serializer;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->serializer = new CanonicalEventSerializer();
    }

    public function exposureRowMatchesTheSchemaExactly(): void
    {
        $row = $this->serializer->exposure(Events::exposure(
            eventId: 'evt-1',
            experiment: 'checkout_button',
            variant: 'b',
            subjectId: 'subject-1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Computed,
            experimentRevision: 'db:7',
            environment: 'prod',
            dimensions: ['country' => 'RU'],
        ));

        Assert::same($row, [
            'v' => 2,
            'event_id' => 'evt-1',
            'occurred_at' => '2026-08-01 10:00:00.123',
            'experiment' => 'checkout_button',
            'variant' => 'b',
            'subject_id' => 'subject-1',
            'decision_reason' => 'assigned',
            'assignment_source' => 'computed',
            'experiment_revision' => 'db:7',
            'environment' => 'prod',
            'dimensions' => '{"country":"RU"}',
        ]);
    }

    public function conversionRowMatchesTheSchemaExactly(): void
    {
        $row = $this->serializer->conversion(Events::conversion(
            eventId: 'evt-2',
            experiment: 'checkout_button',
            variant: 'b',
            subjectId: 'subject-1',
            goal: 'purchase',
            reason: DecisionReason::FallbackDisabled,
            source: AssignmentSource::Store,
            experimentRevision: 'db:7',
            environment: 'prod',
            dimensions: ['country' => 'RU'],
            exposureEventId: 'evt-1',
        ));

        Assert::same($row, [
            'v' => 2,
            'event_id' => 'evt-2',
            'occurred_at' => '2026-08-01 10:00:00.123',
            'experiment' => 'checkout_button',
            'variant' => 'b',
            'subject_id' => 'subject-1',
            'goal' => 'purchase',
            'decision_reason' => 'fallback_disabled',
            'assignment_source' => 'store',
            'experiment_revision' => 'db:7',
            'environment' => 'prod',
            'dimensions' => '{"country":"RU"}',
            'exposure_event_id' => 'evt-1',
        ]);
    }

    public function everyValueIsScalarBecauseTheExporterRejectsNestedFields(): void
    {
        $rows = [
            $this->serializer->exposure(Events::exposure(dimensions: ['country' => 'RU'])),
            $this->serializer->conversion(Events::conversion(dimensions: ['country' => 'RU'])),
        ];

        foreach ($rows as $row) {
            foreach ($row as $field => $value) {
                Assert::true(is_scalar($value), sprintf('Field "%s" must be scalar', $field));
            }
        }
    }

    public function dimensionKeysAreSortedSoOutputIsReproducible(): void
    {
        $one = $this->serializer->exposure(Events::exposure(dimensions: ['plan' => 'pro', 'country' => 'RU']));
        $other = $this->serializer->exposure(Events::exposure(dimensions: ['country' => 'RU', 'plan' => 'pro']));

        Assert::same($one['dimensions'], '{"country":"RU","plan":"pro"}');
        Assert::same($one['dimensions'], $other['dimensions']);
    }

    public function emptyDimensionsBecomeAnEmptyJsonObject(): void
    {
        Assert::same($this->serializer->exposure(Events::exposure())['dimensions'], '{}');
        Assert::same($this->serializer->conversion(Events::conversion())['dimensions'], '{}');
    }

    public function absentOptionalValuesBecomeEmptyStrings(): void
    {
        $exposure = $this->serializer->exposure(Events::exposure());
        $conversion = $this->serializer->conversion(Events::conversion());

        Assert::same($exposure['experiment_revision'], '');
        Assert::same($conversion['experiment_revision'], '');
        Assert::same($conversion['exposure_event_id'], '');
    }

    public function unicodeAndSlashesStayReadableInDimensions(): void
    {
        $row = $this->serializer->exposure(Events::exposure(dimensions: [
            'city' => 'Москва',
            'path' => '/checkout',
        ]));

        Assert::same($row['dimensions'], '{"city":"Москва","path":"/checkout"}');
    }

    public function nonStringDimensionValuesArePreserved(): void
    {
        $row = $this->serializer->exposure(Events::exposure(dimensions: [
            'age' => 42,
            'beta' => true,
            'score' => 1.5,
        ]));

        Assert::same($row['dimensions'], '{"age":42,"beta":true,"score":1.5}');
    }

    public function timestampKeepsMillisecondPrecision(): void
    {
        $row = $this->serializer->exposure(Events::exposure(occurredAt: '2026-08-01 10:00:00.007'));

        Assert::same($row['occurred_at'], '2026-08-01 10:00:00.007');
    }

    public function schemaVersionIsTwo(): void
    {
        Assert::same(CanonicalEventSerializer::SCHEMA_VERSION, 2);
    }
}
