<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Clock frozen at a known instant, so event timestamps are assertable.
 */
final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $now = '2026-08-01 10:00:00.123', string $timezone = 'UTC')
    {
        $this->now = new DateTimeImmutable($now, new DateTimeZone($timezone));
    }

    #[\Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));
    }
}
