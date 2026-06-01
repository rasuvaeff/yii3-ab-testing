<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class NullExposureTracker implements ExposureTracker
{
    #[\Override]
    public function trackExposure(Assignment $assignment): void {}
}
