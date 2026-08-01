<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;

/**
 * Wall-clock time in UTC. The default clock so the package works without
 * wiring; inject a fixed clock in tests.
 *
 * @api
 */
final readonly class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(timezone: new DateTimeZone('UTC'));
    }
}
