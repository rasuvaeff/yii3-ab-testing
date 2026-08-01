<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use InvalidArgumentException;

/**
 * How long after an exposure a conversion still counts for it.
 *
 * A report is meaningless without this bound: without a window, a conversion
 * months later is credited to an experiment the subject no longer sees. The
 * contract lives in the core so every reporting backend answers with the same
 * rule; the query implementation lives in the analytics package.
 *
 * @api
 */
final readonly class AttributionWindow
{
    private const int SECONDS_PER_DAY = 86400;

    /** The value used when an application does not choose one. */
    public const int DEFAULT_DAYS = 7;

    /**
     * @param positive-int $seconds
     */
    private function __construct(
        public int $seconds,
    ) {}

    public static function default(): self
    {
        return self::ofDays(self::DEFAULT_DAYS);
    }

    public static function ofDays(int $days): self
    {
        return self::ofSeconds($days * self::SECONDS_PER_DAY);
    }

    public static function ofSeconds(int $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException(
                sprintf('Attribution window must be positive, got %d seconds', $seconds),
            );
        }

        return new self($seconds);
    }
}
