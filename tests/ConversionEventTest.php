<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\ConversionEvent;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\Internal\EventFields;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(ConversionEvent::class)]
#[Covers(EventFields::class)]
final class ConversionEventTest
{
    public function keepsEveryFieldItWasGiven(): void
    {
        $event = Events::conversion(
            eventId: 'evt-2',
            experiment: 'checkout_button',
            variant: 'b',
            subjectId: 'subject-1',
            goal: 'purchase',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Store,
            experimentRevision: 'db:7',
            environment: 'production',
            dimensions: ['country' => 'RU'],
            exposureEventId: 'evt-1',
        );

        Assert::same($event->eventId, 'evt-2');
        Assert::same($event->goal, 'purchase');
        Assert::same($event->exposureEventId, 'evt-1');
        Assert::same($event->experimentRevision, 'db:7');
        Assert::same($event->dimensions, ['country' => 'RU']);
        Assert::same($event->source, AssignmentSource::Store);
    }

    public function normalisesTheTimestampToUtc(): void
    {
        $event = new ConversionEvent(
            eventId: 'evt-2',
            occurredAt: new DateTimeImmutable('2026-08-01 13:00:00.500', new DateTimeZone('Europe/Moscow')),
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            goal: 'purchase',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Computed,
        );

        Assert::same($event->occurredAt->getTimezone()->getName(), 'UTC');
        Assert::same($event->occurredAt->format('Y-m-d H:i:s.v'), '2026-08-01 10:00:00.500');
    }

    public function conversionWithoutAReceiptHasNoExposureLink(): void
    {
        Assert::null(Events::conversion()->exposureEventId);
    }

    public function onlyAssignedConversionsAreAnalyzable(): void
    {
        Assert::true(Events::conversion(reason: DecisionReason::Assigned)->isAnalyzable());
        Assert::false(Events::conversion(reason: DecisionReason::Forced)->isAnalyzable());
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankGoal(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "goal" must not be empty');

        Events::conversion(goal: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankExposureEventId(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "exposureEventId" must not be empty');

        Events::conversion(exposureEventId: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankEventId(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "eventId" must not be empty');

        Events::conversion(eventId: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankExperiment(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "experiment" must not be empty');

        Events::conversion(experiment: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankVariant(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "variant" must not be empty');

        Events::conversion(variant: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankSubjectId(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "subjectId" must not be empty');

        Events::conversion(subjectId: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankRevision(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "experimentRevision" must not be empty');

        Events::conversion(experimentRevision: $value);
    }

    #[DataProvider('blankProvider')]
    public function rejectsBlankDimensionName(string $value): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "dimensions" must not be empty');

        Events::conversion(dimensions: [$value => 'x']);
    }

    public static function blankProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'whitespace' => ["\t\n"];
    }
}
