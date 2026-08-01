<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\CompositeConversionTracker;
use Rasuvaeff\Yii3AbTesting\CompositeExposureTracker;
use Rasuvaeff\Yii3AbTesting\NullConversionTracker;
use Rasuvaeff\Yii3AbTesting\NullExposureTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Rasuvaeff\Yii3AbTesting\Tests\Support\RecordingConversionTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\RecordingExposureTracker;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(CompositeExposureTracker::class)]
#[Covers(CompositeConversionTracker::class)]
final class CompositeTrackerTest
{
    public function exposureIsForwardedToEveryTrackerInOrder(): void
    {
        $a = new RecordingExposureTracker('first');
        $b = new RecordingExposureTracker('second');
        $composite = new CompositeExposureTracker($a, $b);

        $composite->trackExposure(Events::exposure(experiment: 'exp', variant: 'green'));

        Assert::same($a->trace(), ['first:exp:green']);
        Assert::same($b->trace(), ['second:exp:green']);
    }

    public function exposureCompositeForwardsTheSameEventInstance(): void
    {
        $tracker = new RecordingExposureTracker();
        $event = Events::exposure();

        (new CompositeExposureTracker($tracker))->trackExposure($event);

        Assert::same($tracker->events[0], $event);
    }

    public function conversionIsForwardedToEveryTrackerInOrder(): void
    {
        $a = new RecordingConversionTracker('first');
        $b = new RecordingConversionTracker('second');
        $composite = new CompositeConversionTracker($a, $b);

        $composite->trackConversion(Events::conversion(experiment: 'exp', variant: 'green', goal: 'purchase'));

        Assert::same($a->trace(), ['first:exp:green:purchase']);
        Assert::same($b->trace(), ['second:exp:green:purchase']);
    }

    public function conversionCompositeForwardsTheSameEventInstance(): void
    {
        $tracker = new RecordingConversionTracker();
        $event = Events::conversion();

        (new CompositeConversionTracker($tracker))->trackConversion($event);

        Assert::same($tracker->events[0], $event);
    }

    public function emptyExposureCompositeDoesNothing(): void
    {
        $composite = new CompositeExposureTracker();

        $composite->trackExposure(Events::exposure());
        $composite->flush();

        Assert::true(true);
    }

    public function emptyConversionCompositeDoesNothing(): void
    {
        $composite = new CompositeConversionTracker();

        $composite->trackConversion(Events::conversion());
        $composite->flush();

        Assert::true(true);
    }

    public function exposureFlushReachesEveryFlushableTracker(): void
    {
        $a = new RecordingExposureTracker();
        $b = new RecordingExposureTracker();

        (new CompositeExposureTracker($a, $b))->flush();

        Assert::same($a->flushes, 1);
        Assert::same($b->flushes, 1);
    }

    public function conversionFlushReachesEveryFlushableTracker(): void
    {
        $a = new RecordingConversionTracker();
        $b = new RecordingConversionTracker();

        (new CompositeConversionTracker($a, $b))->flush();

        Assert::same($a->flushes, 1);
        Assert::same($b->flushes, 1);
    }

    public function exposureFlushSkipsTrackersThatCannotFlush(): void
    {
        $flushable = new RecordingExposureTracker();

        (new CompositeExposureTracker(new NullExposureTracker(), $flushable))->flush();

        Assert::same($flushable->flushes, 1);
    }

    public function conversionFlushSkipsTrackersThatCannotFlush(): void
    {
        $flushable = new RecordingConversionTracker();

        (new CompositeConversionTracker(new NullConversionTracker(), $flushable))->flush();

        Assert::same($flushable->flushes, 1);
    }
}
