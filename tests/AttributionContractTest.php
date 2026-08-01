<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\AttributionWindow;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\RepeatedConversionPolicy;
use Rasuvaeff\Yii3AbTesting\SystemClock;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(AttributionWindow::class)]
#[Covers(RepeatedConversionPolicy::class)]
#[Covers(DecisionReason::class)]
#[Covers(AssignmentSource::class)]
#[Covers(SystemClock::class)]
final class AttributionContractTest
{
    public function defaultWindowIsSevenDays(): void
    {
        Assert::same(AttributionWindow::default()->seconds, 7 * 86400);
        Assert::same(AttributionWindow::DEFAULT_DAYS, 7);
    }

    public function windowConvertsDaysToSeconds(): void
    {
        Assert::same(AttributionWindow::ofDays(3)->seconds, 259_200);
    }

    public function windowAcceptsRawSeconds(): void
    {
        Assert::same(AttributionWindow::ofSeconds(90)->seconds, 90);
    }

    #[DataProvider('nonPositiveProvider')]
    public function windowRejectsNonPositiveDurations(int $seconds): void
    {
        Expect::exception(InvalidArgumentException::class)
            ->withMessage(sprintf('Attribution window must be positive, got %d seconds', $seconds));

        AttributionWindow::ofSeconds($seconds);
    }

    public static function nonPositiveProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function windowRejectsNonPositiveDayCounts(): void
    {
        Expect::exception(InvalidArgumentException::class);

        AttributionWindow::ofDays(0);
    }

    public function repeatedConversionDefaultsAreNamed(): void
    {
        Assert::same(RepeatedConversionPolicy::FirstOnly->value, 'first_only');
        Assert::same(RepeatedConversionPolicy::All->value, 'all');
    }

    #[DataProvider('reasonProvider')]
    public function decisionReasonClassifiesFallbackAndAnalyzability(
        DecisionReason $reason,
        string $value,
        bool $isFallback,
        bool $isAnalyzable,
    ): void {
        Assert::same($reason->value, $value);
        Assert::same($reason->isFallback(), $isFallback);
        Assert::same($reason->isAnalyzable(), $isAnalyzable);
    }

    public static function reasonProvider(): iterable
    {
        yield 'assigned' => [DecisionReason::Assigned, 'assigned', false, true];
        yield 'forced' => [DecisionReason::Forced, 'forced', false, false];
        yield 'disabled' => [DecisionReason::FallbackDisabled, 'fallback_disabled', true, false];
        yield 'targeting' => [
            DecisionReason::FallbackTargetingMismatch,
            'fallback_targeting_mismatch',
            true,
            false,
        ];
    }

    public function assignmentSourceValuesAreStable(): void
    {
        Assert::same(AssignmentSource::Computed->value, 'computed');
        Assert::same(AssignmentSource::Store->value, 'store');
    }

    /**
     * `fallback_unspecified` is produced only by backfills of pre-v2 data, so
     * the enum must not accept it — it can be read from storage, never minted.
     */
    public function decisionReasonRejectsTheBackfillOnlyValue(): void
    {
        Assert::null(DecisionReason::tryFrom('fallback_unspecified'));
    }

    public function systemClockReturnsUtcTime(): void
    {
        $now = (new SystemClock())->now();

        Assert::same($now->getTimezone()->getName(), 'UTC');
        Assert::true(abs($now->getTimestamp() - time()) <= 1);
    }
}
