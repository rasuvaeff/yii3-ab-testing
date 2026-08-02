<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\ExposureEvent;
use Rasuvaeff\Yii3AbTesting\Internal\EventFields;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(ExposureEvent::class)]
#[Covers(EventFields::class)]
final class ExposureEventTest
{
    public function keepsEveryFieldItWasGiven(): void
    {
        $event = Events::exposure(
            eventId: 'evt-1',
            experiment: 'checkout_button',
            variant: 'b',
            subjectId: 'subject-1',
            reason: DecisionReason::Forced,
            source: AssignmentSource::Store,
            experimentRevision: 'db:7',
            environment: 'production',
            dimensions: ['country' => 'RU'],
        );

        Assert::same($event->eventId, 'evt-1');
        Assert::same($event->experiment, 'checkout_button');
        Assert::same($event->variant, 'b');
        Assert::same($event->subjectId, 'subject-1');
        Assert::same($event->reason, DecisionReason::Forced);
        Assert::same($event->source, AssignmentSource::Store);
        Assert::same($event->experimentRevision, 'db:7');
        Assert::same($event->environment, 'production');
        Assert::same($event->dimensions, ['country' => 'RU']);
    }

    public function normalisesTheTimestampToUtc(): void
    {
        $event = new ExposureEvent(
            eventId: 'evt-1',
            occurredAt: new DateTimeImmutable('2026-08-01 13:00:00.500', new DateTimeZone('Europe/Moscow')),
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Computed,
        );

        Assert::same($event->occurredAt->getTimezone()->getName(), 'UTC');
        Assert::same($event->occurredAt->format('Y-m-d H:i:s.v'), '2026-08-01 10:00:00.500');
    }

    public function optionalFieldsDefaultToAbsent(): void
    {
        $event = new ExposureEvent(
            eventId: 'evt-1',
            occurredAt: Events::time(),
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Computed,
        );

        Assert::null($event->experimentRevision);
        Assert::same($event->environment, '');
        Assert::same($event->dimensions, []);
    }

    public function onlyAssignedExposuresAreAnalyzable(): void
    {
        Assert::true(Events::exposure(reason: DecisionReason::Assigned)->isAnalyzable());
        Assert::false(Events::exposure(reason: DecisionReason::Forced)->isAnalyzable());
        Assert::false(Events::exposure(reason: DecisionReason::FallbackDisabled)->isAnalyzable());
        Assert::false(Events::exposure(reason: DecisionReason::FallbackTargetingMismatch)->isAnalyzable());
    }

    public function stickySourceDoesNotAffectAnalyzability(): void
    {
        Assert::true(Events::exposure(source: AssignmentSource::Store)->isAnalyzable());
    }

    public function preservesEveryDimensionWhenMultipleAreGiven(): void
    {
        $event = Events::exposure(dimensions: ['country' => 'RU', 'plan' => 'pro', 'device' => 'mobile']);

        Assert::same($event->dimensions, ['country' => 'RU', 'plan' => 'pro', 'device' => 'mobile']);
    }

    public function receiptCarriesTheDecisionForALaterRequest(): void
    {
        $event = Events::exposure(
            eventId: 'evt-1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Store,
            experimentRevision: 'db:7',
        );

        $receipt = $event->receipt();

        Assert::same($receipt->exposureEventId, 'evt-1');
        // setTimezone() returns a new instance even when already UTC, so the
        // receipt holds an equal — not identical — timestamp.
        Assert::same(
            $receipt->occurredAt->format('Y-m-d H:i:s.v'),
            $event->occurredAt->format('Y-m-d H:i:s.v'),
        );
        Assert::same($receipt->experiment, $event->experiment);
        Assert::same($receipt->variant, $event->variant);
        Assert::same($receipt->subjectId, $event->subjectId);
        Assert::same($receipt->reason, $event->reason);
        Assert::same($receipt->source, $event->source);
        Assert::same($receipt->experimentRevision, 'db:7');
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankEventId(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "eventId" must not be empty');

        Events::exposure(eventId: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankExperiment(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "experiment" must not be empty');

        Events::exposure(experiment: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankVariant(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "variant" must not be empty');

        Events::exposure(variant: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankSubjectId(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "subjectId" must not be empty');

        Events::exposure(subjectId: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankRevisionButAcceptsNull(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "experimentRevision" must not be empty');

        Events::exposure(experimentRevision: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankDimensionName(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "dimensions" must not be empty');

        Events::exposure(dimensions: [$value => 'x']);
    }

    public static function blankProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'whitespace' => ["\t\n"];
    }
}
