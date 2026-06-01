<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\NullConversionTracker;
use Rasuvaeff\Yii3AbTesting\NullExposureTracker;

#[CoversClass(NullExposureTracker::class)]
#[CoversClass(NullConversionTracker::class)]
final class NullTrackerTest extends TestCase
{
    #[Test]
    public function nullExposureTrackerDoesNotThrow(): void
    {
        $tracker = new NullExposureTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        $this->expectNotToPerformAssertions();

        $tracker->trackExposure($assignment);
    }

    #[Test]
    public function nullConversionTrackerDoesNotThrow(): void
    {
        $tracker = new NullConversionTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        $this->expectNotToPerformAssertions();

        $tracker->trackConversion($assignment, goal: 'purchase');
    }
}
