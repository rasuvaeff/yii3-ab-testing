<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\CompositeConversionTracker;
use Rasuvaeff\Yii3AbTesting\CompositeExposureTracker;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\FlushableTracker;

#[CoversClass(CompositeExposureTracker::class)]
#[CoversClass(CompositeConversionTracker::class)]
final class CompositeTrackerTest extends TestCase
{
    #[Test]
    public function exposureIsForwardedToEveryTrackerInOrder(): void
    {
        $a = $this->recordingExposure('first');
        $b = $this->recordingExposure('second');
        $composite = new CompositeExposureTracker($a, $b);
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $composite->trackExposure($assignment);

        $this->assertSame(['first:exp:green'], $a->calls);
        $this->assertSame(['second:exp:green'], $b->calls);
    }

    #[Test]
    public function conversionIsForwardedToEveryTrackerInOrder(): void
    {
        $a = $this->recordingConversion('first');
        $b = $this->recordingConversion('second');
        $composite = new CompositeConversionTracker($a, $b);
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $composite->trackConversion($assignment, goal: 'purchase');

        $this->assertSame(['first:exp:green:purchase'], $a->calls);
        $this->assertSame(['second:exp:green:purchase'], $b->calls);
    }

    #[Test]
    public function emptyExposureCompositeDoesNothing(): void
    {
        $composite = new CompositeExposureTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $this->expectNotToPerformAssertions();

        $composite->trackExposure($assignment);
    }

    #[Test]
    public function emptyConversionCompositeDoesNothing(): void
    {
        $composite = new CompositeConversionTracker();
        $assignment = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'u1');

        $this->expectNotToPerformAssertions();

        $composite->trackConversion($assignment, goal: 'purchase');
    }

    #[Test]
    public function exposureFlushReachesOnlyFlushableTrackers(): void
    {
        $plain = $this->recordingExposure('plain');
        $flushable = $this->flushableExposure();
        $composite = new CompositeExposureTracker($plain, $flushable);

        $composite->flush();

        $this->assertSame(1, $flushable->flushes);
        $this->assertSame([], $plain->calls);
    }

    #[Test]
    public function conversionFlushReachesOnlyFlushableTrackers(): void
    {
        $plain = $this->recordingConversion('plain');
        $flushable = $this->flushableConversion();
        $composite = new CompositeConversionTracker($plain, $flushable);

        $composite->flush();

        $this->assertSame(1, $flushable->flushes);
        $this->assertSame([], $plain->calls);
    }

    /**
     * @return ExposureTracker&FlushableTracker&object{flushes: int}
     */
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

    /**
     * @return ConversionTracker&FlushableTracker&object{flushes: int}
     */
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

    /**
     * @return ExposureTracker&object{calls: list<string>}
     */
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

    /**
     * @return ConversionTracker&object{calls: list<string>}
     */
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
