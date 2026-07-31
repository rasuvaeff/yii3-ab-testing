<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\DeduplicatingExposureTracker;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\FlushableTracker;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(DeduplicatingExposureTracker::class)]
final class DeduplicatingExposureTrackerTest
{
    /** @var list<Assignment> */
    private array $exposures = [];

    private DeduplicatingExposureTracker $tracker;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->exposures = [];
        $exposures = &$this->exposures;
        $delegate = new class ($exposures) implements ExposureTracker {
            /** @param list<Assignment> $exposures */
            public function __construct(private array &$exposures) {}

            #[\Override]
            public function trackExposure(Assignment $assignment): void
            {
                $this->exposures[] = $assignment;
            }
        };
        $this->tracker = new DeduplicatingExposureTracker(tracker: $delegate);
    }

    public function suppressesSameExperimentSubjectAndConfiguration(): void
    {
        $first = $this->assignment(experiment: 'checkout', subject: 'u1', configuration: 'rev-1');
        $second = new Assignment(
            experiment: 'checkout',
            variant: 'green',
            subjectId: 'u1',
            configurationId: 'rev-1',
        );

        $this->tracker->trackExposure($first);
        $this->tracker->trackExposure($second);

        Assert::same($this->exposures, [$first]);
    }

    public function tracksDifferentSubjectsConfigurationsAndExperiments(): void
    {
        $assignments = [
            $this->assignment(experiment: 'checkout', subject: 'u1', configuration: 'rev-1'),
            $this->assignment(experiment: 'checkout', subject: 'u2', configuration: 'rev-1'),
            $this->assignment(experiment: 'checkout', subject: 'u1', configuration: 'rev-2'),
            $this->assignment(experiment: 'pricing', subject: 'u1', configuration: 'rev-1'),
        ];

        foreach ($assignments as $assignment) {
            $this->tracker->trackExposure($assignment);
        }

        Assert::same($this->exposures, $assignments);
    }

    public function resetStartsNewRequestScope(): void
    {
        $assignment = $this->assignment(experiment: 'checkout', subject: 'u1', configuration: 'rev-1');
        $this->tracker->trackExposure($assignment);
        $this->tracker->reset();
        $this->tracker->trackExposure($assignment);

        Assert::same($this->exposures, [$assignment, $assignment]);
    }

    public function flushPropagatesToFlushableDelegate(): void
    {
        $delegate = new class implements ExposureTracker, FlushableTracker {
            public bool $flushed = false;

            #[\Override]
            public function trackExposure(Assignment $assignment): void {}

            #[\Override]
            public function flush(): void
            {
                $this->flushed = true;
            }
        };
        $tracker = new DeduplicatingExposureTracker(tracker: $delegate);

        $tracker->flush();

        Assert::true($delegate->flushed);
    }

    private function assignment(string $experiment, string $subject, string $configuration): Assignment
    {
        return new Assignment(
            experiment: $experiment,
            variant: 'control',
            subjectId: $subject,
            configurationId: $configuration,
        );
    }
}
