<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\CanonicalEventSerializer;
use Rasuvaeff\Yii3AbTesting\ConversionEvent;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\ExposureEvent;
use Rasuvaeff\Yii3AbTesting\LoggerConversionTracker;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Test\Support\Log\SimpleLogger;

/**
 * The cross-package acceptance check. Adapters read the same file from
 * `vendor/rasuvaeff/yii3-ab-testing/fixtures/` and assert the same rows, so a
 * field that disappears on one delivery path fails a test instead of silently
 * vanishing between packages — the defect that motivated schema v2.
 */
#[Test]
#[Covers(CanonicalEventSerializer::class)]
final class GoldenEventFixtureTest
{
    public const string FIXTURE = __DIR__ . '/../fixtures/golden-event-v2.json';

    /** @var array{exposure: array{input: array<string, mixed>, row: array<string, mixed>}, conversion: array{input: array<string, mixed>, row: array<string, mixed>}} */
    private array $fixture;

    #[BeforeTest]
    public function setUp(): void
    {
        $contents = file_get_contents(self::FIXTURE);
        Assert::true($contents !== false, 'Golden fixture must ship with the package');

        /** @var array{exposure: array{input: array<string, mixed>, row: array<string, mixed>}, conversion: array{input: array<string, mixed>, row: array<string, mixed>}} $decoded */
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $this->fixture = $decoded;
    }

    public function exposureSerializesToTheGoldenRow(): void
    {
        $row = (new CanonicalEventSerializer())->exposure($this->exposureEvent());

        Assert::same($row, $this->fixture['exposure']['row']);
    }

    public function conversionSerializesToTheGoldenRow(): void
    {
        $row = (new CanonicalEventSerializer())->conversion($this->conversionEvent());

        Assert::same($row, $this->fixture['conversion']['row']);
    }

    /**
     * The point of the fixture: no domain field may be absent from the row.
     * `ingested_at` is the only legitimate omission — storage fills it.
     */
    public function noDomainFieldIsLostOnTheWay(): void
    {
        $row = (new CanonicalEventSerializer())->exposure($this->exposureEvent());
        $event = $this->exposureEvent();

        $carried = [
            $event->eventId,
            $event->experiment,
            $event->variant,
            $event->subjectId,
            $event->reason->value,
            $event->source->value,
            $event->experimentRevision,
            $event->environment,
        ];

        foreach ($carried as $value) {
            Assert::true(
                \in_array($value, array_map(strval(...), $row), true),
                sprintf('Value "%s" must survive serialization', (string) $value),
            );
        }

        foreach (array_keys($event->dimensions) as $dimension) {
            Assert::string($row['dimensions'])->contains($dimension);
        }
    }

    public function loggerSinkShipsExactlyTheGoldenRow(): void
    {
        $logger = new SimpleLogger();

        (new LoggerExposureTracker(logger: $logger))->trackExposure($this->exposureEvent());

        Assert::same($logger->getMessages()[0]['context']['event'], $this->fixture['exposure']['row']);
    }

    public function loggerConversionSinkShipsExactlyTheGoldenRow(): void
    {
        $logger = new SimpleLogger();

        (new LoggerConversionTracker(logger: $logger))->trackConversion($this->conversionEvent());

        Assert::same($logger->getMessages()[0]['context']['event'], $this->fixture['conversion']['row']);
    }

    private function exposureEvent(): ExposureEvent
    {
        /** @var array{eventId: string, occurredAt: string, experiment: string, variant: string, subjectId: string, reason: string, source: string, experimentRevision: string, environment: string, dimensions: array<string, scalar>} $input */
        $input = $this->fixture['exposure']['input'];

        return new ExposureEvent(
            eventId: $input['eventId'],
            occurredAt: $this->time($input['occurredAt']),
            experiment: $input['experiment'],
            variant: $input['variant'],
            subjectId: $input['subjectId'],
            reason: DecisionReason::from($input['reason']),
            source: AssignmentSource::from($input['source']),
            experimentRevision: $input['experimentRevision'],
            environment: $input['environment'],
            dimensions: $input['dimensions'],
        );
    }

    private function conversionEvent(): ConversionEvent
    {
        /** @var array{eventId: string, occurredAt: string, experiment: string, variant: string, subjectId: string, goal: string, reason: string, source: string, experimentRevision: string, environment: string, dimensions: array<string, scalar>, exposureEventId: string} $input */
        $input = $this->fixture['conversion']['input'];

        return new ConversionEvent(
            eventId: $input['eventId'],
            occurredAt: $this->time($input['occurredAt']),
            experiment: $input['experiment'],
            variant: $input['variant'],
            subjectId: $input['subjectId'],
            goal: $input['goal'],
            reason: DecisionReason::from($input['reason']),
            source: AssignmentSource::from($input['source']),
            experimentRevision: $input['experimentRevision'],
            environment: $input['environment'],
            dimensions: $input['dimensions'],
            exposureEventId: $input['exposureEventId'],
        );
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
