<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Fans a single exposure out to several {@see ExposureTracker} sinks in order.
 *
 * Only one tracker package may bind {@see ExposureTracker} in the `di` group
 * (one-source rule). To send exposures to several sinks at once, the application
 * binds this composite in its own root-layer config.
 *
 * @api
 */
final readonly class CompositeExposureTracker implements ExposureTracker, FlushableTracker
{
    /**
     * @var array<array-key, ExposureTracker>
     */
    private array $trackers;

    public function __construct(ExposureTracker ...$trackers)
    {
        $this->trackers = $trackers;
    }

    #[\Override]
    public function trackExposure(Assignment $assignment): void
    {
        foreach ($this->trackers as $tracker) {
            $tracker->trackExposure($assignment);
        }
    }

    #[\Override]
    public function flush(): void
    {
        foreach ($this->trackers as $tracker) {
            if ($tracker instanceof FlushableTracker) {
                $tracker->flush();
            }
        }
    }
}
