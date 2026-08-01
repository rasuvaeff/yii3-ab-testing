<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3AbTesting\AssignmentReceipt;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(AssignmentReceipt::class)]
final class AssignmentReceiptTest
{
    public function survivesARoundTripThroughItsArrayForm(): void
    {
        $receipt = $this->receipt();

        $restored = AssignmentReceipt::fromArray($receipt->toArray());

        Assert::same($restored->exposureEventId, $receipt->exposureEventId);
        Assert::same($restored->occurredAt->format('Y-m-d\TH:i:s.vp'), $receipt->occurredAt->format('Y-m-d\TH:i:s.vp'));
        Assert::same($restored->experiment, $receipt->experiment);
        Assert::same($restored->variant, $receipt->variant);
        Assert::same($restored->subjectId, $receipt->subjectId);
        Assert::same($restored->reason, $receipt->reason);
        Assert::same($restored->source, $receipt->source);
        Assert::same($restored->experimentRevision, $receipt->experimentRevision);
    }

    public function roundTripSurvivesAMissingRevision(): void
    {
        $receipt = $this->receipt(revision: null);

        $restored = AssignmentReceipt::fromArray($receipt->toArray());

        Assert::null($restored->experimentRevision);
    }

    public function usesShortKeysToStayWithinCookieLimits(): void
    {
        Assert::same(
            array_keys($this->receipt()->toArray()),
            ['eid', 'at', 'e', 'v', 's', 'r', 'src', 'rev'],
        );
    }

    public function normalisesTheTimestampToUtc(): void
    {
        $receipt = new AssignmentReceipt(
            exposureEventId: 'evt-1',
            occurredAt: new \DateTimeImmutable('2026-08-01 13:00:00', new \DateTimeZone('Europe/Moscow')),
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Computed,
        );

        Assert::same($receipt->occurredAt->getTimezone()->getName(), 'UTC');
    }

    #[DataProvider('missingFieldProvider')]
    public function rejectsAMissingOrNonStringField(string $key): void
    {
        $data = $this->receipt()->toArray();
        unset($data[$key]);

        Expect::exception(InvalidArgumentException::class)
            ->withMessage(sprintf('Receipt field "%s" must be a string', $key));

        AssignmentReceipt::fromArray($data);
    }

    public static function missingFieldProvider(): iterable
    {
        yield 'event id' => ['eid'];
        yield 'timestamp' => ['at'];
        yield 'experiment' => ['e'];
        yield 'variant' => ['v'];
        yield 'subject' => ['s'];
        yield 'reason' => ['r'];
        yield 'source' => ['src'];
    }

    public function rejectsANonStringFieldValue(): void
    {
        $data = $this->receipt()->toArray();
        $data['e'] = 42;

        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Receipt field "e" must be a string');

        AssignmentReceipt::fromArray($data);
    }

    public function rejectsAMalformedTimestamp(): void
    {
        $data = $this->receipt()->toArray();
        $data['at'] = 'yesterday';

        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Receipt field "at" is not a valid timestamp');

        AssignmentReceipt::fromArray($data);
    }

    public function rejectsAnUnknownDecisionReason(): void
    {
        $data = $this->receipt()->toArray();
        $data['r'] = 'fallback_unspecified';

        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Receipt field "r" is not a known decision reason');

        AssignmentReceipt::fromArray($data);
    }

    public function rejectsAnUnknownAssignmentSource(): void
    {
        $data = $this->receipt()->toArray();
        $data['src'] = 'telepathy';

        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Receipt field "src" is not a known assignment source');

        AssignmentReceipt::fromArray($data);
    }

    public function rejectsANonStringRevision(): void
    {
        $data = $this->receipt()->toArray();
        $data['rev'] = 7;

        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Receipt field "rev" must be a string or null');

        AssignmentReceipt::fromArray($data);
    }

    public function rejectsABlankExposureEventId(): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "exposureEventId" must not be empty');

        new AssignmentReceipt(
            exposureEventId: '',
            occurredAt: Events::time(),
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Computed,
        );
    }

    private function receipt(?string $revision = 'db:7'): AssignmentReceipt
    {
        return new AssignmentReceipt(
            exposureEventId: 'evt-1',
            occurredAt: Events::time(),
            experiment: 'checkout_button',
            variant: 'b',
            subjectId: 'subject-1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Store,
            experimentRevision: $revision,
        );
    }
}
