<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\NullConversionTracker;
use Rasuvaeff\Yii3AbTesting\NullExposureTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(NullExposureTracker::class)]
#[Covers(NullConversionTracker::class)]
final class NullTrackerTest
{
    public function nullExposureTrackerDoesNotThrow(): void
    {
        (new NullExposureTracker())->trackExposure(Events::exposure());

        Assert::true(true);
    }

    public function nullConversionTrackerDoesNotThrow(): void
    {
        (new NullConversionTracker())->trackConversion(Events::conversion());

        Assert::true(true);
    }
}
