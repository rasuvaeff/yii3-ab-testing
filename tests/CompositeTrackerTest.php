<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\CompositeConversionTracker;
use Rasuvaeff\Yii3AbTesting\CompositeExposureTracker;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\FlushableTracker;
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
        $a = $this->recordingExposure('first');
        $b = $this->recordingExposure('second');
        $composite = new CompositeExposureTracker($a, $b);
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $composite->trackExposure($assignment);

        Assert::same($a->calls, ['first:exp:green']);
        Assert::same($b->calls, ['second:exp:green']);
    }

    public function conversionIsForwardedToEveryTrackerInOrder(): void
    {
        $a = $this->recordingConversion('first');
        $b = $this->recordingConversion('second');
        $composite = new CompositeConversionTracker($a, $b);
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $composite->trackConversion($assignment, goal: 'purchase');

        Assert::same($a->calls, ['first:exp:green:purchase']);
        Assert::same($b->calls, ['second:exp:green:purchase']);
    }

    public function emptyExposureCompositeDoesNothing(): void
    {
        $composite = new CompositeExposureTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $composite->trackExposure($assignment);

        Assert::true(true);
    }

    public function emptyConversionCompositeDoesNothing(): void
    {
        $composite = new CompositeConversionTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $composite->trackConversion($assignment, goal: 'purchase');

        Assert::true(true);
    }

    public function exposureFlushReachesOnlyFlushableTrackers(): void
    {
        $plain = $this->recordingExposure('plain');
        $flushable = $this->flushableExposure();
        $composite = new CompositeExposureTracker($plain, $flushable);

        $composite->flush();

        Assert::same($flushable->flushes, 1);
        Assert::same($plain->calls, []);
    }

    public function conversionFlushReachesOnlyFlushableTrackers(): void
    {
        $plain = $this->recordingConversion('plain');
        $flushable = $this->flushableConversion();
        $composite = new CompositeConversionTracker($plain, $flushable);

        $composite->flush();

        Assert::same($flushable->flushes, 1);
        Assert::same($plain->calls, []);
    }

    private function flushableExposure(): ExposureTracker&FlushableTracker
    {
        return new class implements ExposureTracker, FlushableTracker {
            public int $flushes = 0;

            #[\Override]
            public function trackExposure(Assignment $assignment): void {}

            #[\Override]
            public function flush(): void
            {
                ++$this->flushes;
            }
        };
    }

    private function flushableConversion(): ConversionTracker&FlushableTracker
    {
        return new class implements ConversionTracker, FlushableTracker {
            public int $flushes = 0;

            #[\Override]
            public function trackConversion(Assignment $assignment, string $goal): void {}

            #[\Override]
            public function flush(): void
            {
                ++$this->flushes;
            }
        };
    }

    private function recordingExposure(string $tag): ExposureTracker
    {
        return new class ($tag) implements ExposureTracker {
            /** @var list<string> */
            public array $calls = [];

            public function __construct(private readonly string $tag) {}

            #[\Override]
            public function trackExposure(Assignment $assignment): void
            {
                $this->calls[] = sprintf('%s:%s:%s', $this->tag, $assignment->experiment, $assignment->variant);
            }
        };
    }

    private function recordingConversion(string $tag): ConversionTracker
    {
        return new class ($tag) implements ConversionTracker {
            /** @var list<string> */
            public array $calls = [];

            public function __construct(private readonly string $tag) {}

            #[\Override]
            public function trackConversion(Assignment $assignment, string $goal): void
            {
                $this->calls[] = sprintf('%s:%s:%s:%s', $this->tag, $assignment->experiment, $assignment->variant, $goal);
            }
        };
    }
}
