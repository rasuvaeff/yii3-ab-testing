<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Receives conversions for delivery to analytics storage.
 *
 * The goal now travels inside {@see ConversionEvent}, which validates it, so a
 * sink can no longer be handed an empty one.
 *
 * @api
 */
interface ConversionTracker
{
    public function trackConversion(ConversionEvent $event): void;
}
