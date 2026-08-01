<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Discards exposures. The default sink, so tracking calls are safe before any
 * analytics backend is wired.
 *
 * @api
 */
final readonly class NullExposureTracker implements ExposureTracker
{
    #[\Override]
    public function trackExposure(ExposureEvent $event): void {}
}
