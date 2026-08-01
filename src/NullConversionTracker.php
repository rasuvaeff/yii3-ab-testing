<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Discards conversions. The default sink, so tracking calls are safe before any
 * analytics backend is wired.
 *
 * @api
 */
final readonly class NullConversionTracker implements ConversionTracker
{
    #[\Override]
    public function trackConversion(ConversionEvent $event): void {}
}
