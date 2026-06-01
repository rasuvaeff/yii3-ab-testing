<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class NullConversionTracker implements ConversionTracker
{
    #[\Override]
    public function trackConversion(Assignment $assignment, string $goal): void {}
}
