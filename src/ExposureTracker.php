<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Receives exposures for delivery to analytics storage.
 *
 * Takes a fully-formed {@see ExposureEvent} rather than an {@see Assignment}:
 * event identity, timestamp and the context allow-list are minted once by the
 * facade, so every sink records the same fields. Sinks that built their own
 * representation were exactly how v1 lost `isTargetingMismatch` on one path and
 * context attributes on another.
 *
 * @api
 */
interface ExposureTracker
{
    public function trackExposure(ExposureEvent $event): void;
}
