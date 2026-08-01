<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests\Support;

use Rasuvaeff\Yii3AbTesting\EventIdGenerator;

/**
 * Predictable ids (`evt-1`, `evt-2`, …) so assertions can name the exact event
 * a conversion should point at.
 */
final class SequentialEventIdGenerator implements EventIdGenerator
{
    private int $next = 1;

    public function __construct(
        private readonly string $prefix = 'evt-',
    ) {}

    #[\Override]
    public function generate(): string
    {
        return $this->prefix . $this->next++;
    }
}
