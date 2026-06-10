<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Fans a single conversion out to several {@see ConversionTracker} sinks in order.
 *
 * Only one tracker package may bind {@see ConversionTracker} in the `di` group
 * (one-source rule). To send conversions to several sinks at once, the
 * application binds this composite in its own root-layer config.
 *
 * @api
 */
final readonly class CompositeConversionTracker implements ConversionTracker
{
    /**
     * @var array<array-key, ConversionTracker>
     */
    private array $trackers;

    public function __construct(ConversionTracker ...$trackers)
    {
        $this->trackers = $trackers;
    }

    #[\Override]
    public function trackConversion(Assignment $assignment, string $goal): void
    {
        foreach ($this->trackers as $tracker) {
            $tracker->trackConversion($assignment, goal: $goal);
        }
    }
}
