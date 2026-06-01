<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface ConversionTracker
{
    public function trackConversion(Assignment $assignment, string $goal): void;
}
