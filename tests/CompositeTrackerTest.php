<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3AbTesting\CompositeConversionTracker;
use Rasuvaeff\Yii3AbTesting\CompositeExposureTracker;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\FlushableTracker;
use Rasuvaeff\Yii3AbTesting\NullConversionTracker;
use Rasuvaeff\Yii3AbTesting\NullExposureTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

use function Rasuvaeff\Understudy\verify;

#[Test]
#[Covers(CompositeExposureTracker::class)]
#[Covers(CompositeConversionTracker::class)]
final class CompositeTrackerTest
{
    public function exposureIsForwardedToEveryTrackerInOrder(): void
    {
        $a = Understudy::for(ExposureTracker::class);
        $b = Understudy::for(ExposureTracker::class);
        $event = Events::exposure(experiment: 'exp', variant: 'green');
        $composite = new CompositeExposureTracker($a, $b);

        $composite->trackExposure($event);

        Understudy::verifySequence(
            fn() => $a->trackExposure($event),
            fn() => $b->trackExposure($event),
        );
    }

    public function exposureCompositeForwardsTheSameEventInstance(): void
    {
        $tracker = Understudy::for(ExposureTracker::class);
        $event = Events::exposure();

        (new CompositeExposureTracker($tracker))->trackExposure($event);

        verify(fn() => $tracker->trackExposure($event), times: 1);
    }

    public function conversionIsForwardedToEveryTrackerInOrder(): void
    {
        $a = Understudy::for(ConversionTracker::class);
        $b = Understudy::for(ConversionTracker::class);
        $event = Events::conversion(experiment: 'exp', variant: 'green', goal: 'purchase');
        $composite = new CompositeConversionTracker($a, $b);

        $composite->trackConversion($event);

        Understudy::verifySequence(
            fn() => $a->trackConversion($event),
            fn() => $b->trackConversion($event),
        );
    }

    public function conversionCompositeForwardsTheSameEventInstance(): void
    {
        $tracker = Understudy::for(ConversionTracker::class);
        $event = Events::conversion();

        (new CompositeConversionTracker($tracker))->trackConversion($event);

        verify(fn() => $tracker->trackConversion($event), times: 1);
    }

    public function emptyExposureCompositeDoesNothing(): void
    {
        $composite = new CompositeExposureTracker();

        $composite->trackExposure(Events::exposure());
        $composite->flush();

        Assert::true(actual: true);
    }

    public function emptyConversionCompositeDoesNothing(): void
    {
        $composite = new CompositeConversionTracker();

        $composite->trackConversion(Events::conversion());
        $composite->flush();

        Assert::true(actual: true);
    }

    public function exposureFlushReachesEveryFlushableTracker(): void
    {
        $a = Understudy::for(ExposureTracker::class, FlushableTracker::class);
        $b = Understudy::for(ExposureTracker::class, FlushableTracker::class);

        (new CompositeExposureTracker($a, $b))->flush();

        verify(fn() => $a->flush(), times: 1);
        verify(fn() => $b->flush(), times: 1);
    }

    public function conversionFlushReachesEveryFlushableTracker(): void
    {
        $a = Understudy::for(ConversionTracker::class, FlushableTracker::class);
        $b = Understudy::for(ConversionTracker::class, FlushableTracker::class);

        (new CompositeConversionTracker($a, $b))->flush();

        verify(fn() => $a->flush(), times: 1);
        verify(fn() => $b->flush(), times: 1);
    }

    public function exposureFlushSkipsTrackersThatCannotFlush(): void
    {
        $flushable = Understudy::for(ExposureTracker::class, FlushableTracker::class);

        (new CompositeExposureTracker(new NullExposureTracker(), $flushable))->flush();

        verify(fn() => $flushable->flush(), times: 1);
    }

    public function conversionFlushSkipsTrackersThatCannotFlush(): void
    {
        $flushable = Understudy::for(ConversionTracker::class, FlushableTracker::class);

        (new CompositeConversionTracker(new NullConversionTracker(), $flushable))->flush();

        verify(fn() => $flushable->flush(), times: 1);
    }
}
