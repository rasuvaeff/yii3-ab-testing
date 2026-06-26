<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\NullConversionTracker;
use Rasuvaeff\Yii3AbTesting\NullExposureTracker;
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
        $tracker = new NullExposureTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        $tracker->trackExposure($assignment);

        Assert::true(true);
    }

    public function nullConversionTrackerDoesNotThrow(): void
    {
        $tracker = new NullConversionTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        $tracker->trackConversion($assignment, goal: 'purchase');

        Assert::true(true);
    }
}
