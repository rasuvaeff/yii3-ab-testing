<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests\Support;

use Rasuvaeff\Yii3AbTesting\ExposureEvent;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\FlushableTracker;

/**
 * Captures the events it receives, and records whether it was flushed.
 */
final class RecordingExposureTracker implements ExposureTracker, FlushableTracker
{
    /** @var list<ExposureEvent> */
    public array $events = [];

    public int $flushes = 0;

    public function __construct(
        private readonly string $name = 'tracker',
    ) {}

    #[\Override]
    public function trackExposure(ExposureEvent $event): void
    {
        $this->events[] = $event;
    }

    #[\Override]
    public function flush(): void
    {
        ++$this->flushes;
    }

    /**
     * @return list<string> `name:experiment:variant` per received event, for
     *     order-sensitive assertions.
     */
    public function trace(): array
    {
        return array_map(
            fn (ExposureEvent $event): string => sprintf('%s:%s:%s', $this->name, $event->experiment, $event->variant),
            $this->events,
        );
    }
}
