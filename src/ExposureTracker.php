<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface ExposureTracker
{
    public function trackExposure(Assignment $assignment): void;
}
