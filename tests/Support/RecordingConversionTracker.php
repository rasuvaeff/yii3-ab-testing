<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests\Support;

use Rasuvaeff\Yii3AbTesting\ConversionEvent;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;
use Rasuvaeff\Yii3AbTesting\FlushableTracker;

/**
 * Captures the events it receives, and records whether it was flushed.
 */
final class RecordingConversionTracker implements ConversionTracker, FlushableTracker
{
    /** @var list<ConversionEvent> */
    public array $events = [];

    public int $flushes = 0;

    public function __construct(
        private readonly string $name = 'tracker',
    ) {}

    #[\Override]
    public function trackConversion(ConversionEvent $event): void
    {
        $this->events[] = $event;
    }

    #[\Override]
    public function flush(): void
    {
        ++$this->flushes;
    }

    /**
     * @return list<string> `name:experiment:variant:goal` per received event.
     */
    public function trace(): array
    {
        return array_map(
            fn(ConversionEvent $event): string => sprintf(
                '%s:%s:%s:%s',
                $this->name,
                $event->experiment,
                $event->variant,
                $event->goal,
            ),
            $this->events,
        );
    }
}
