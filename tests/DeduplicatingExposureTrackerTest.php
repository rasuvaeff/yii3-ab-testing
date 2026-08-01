<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\DeduplicatingExposureTracker;
use Rasuvaeff\Yii3AbTesting\NullExposureTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Rasuvaeff\Yii3AbTesting\Tests\Support\RecordingExposureTracker;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(DeduplicatingExposureTracker::class)]
final class DeduplicatingExposureTrackerTest
{
    private RecordingExposureTracker $delegate;

    private DeduplicatingExposureTracker $tracker;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->delegate = new RecordingExposureTracker();
        $this->tracker = new DeduplicatingExposureTracker(tracker: $this->delegate);
    }

    public function suppressesSameExperimentSubjectAndRevision(): void
    {
        $first = Events::exposure(eventId: 'evt-1', experiment: 'checkout', subjectId: 'u1', experimentRevision: 'rev-1');
        $second = Events::exposure(eventId: 'evt-2', experiment: 'checkout', subjectId: 'u1', experimentRevision: 'rev-1');

        $this->tracker->trackExposure($first);
        $this->tracker->trackExposure($second);

        Assert::same($this->delegate->events, [$first]);
    }

    public function tracksDifferentSubjectsRevisionsAndExperiments(): void
    {
        $events = [
            Events::exposure(eventId: 'evt-1', experiment: 'checkout', subjectId: 'u1', experimentRevision: 'rev-1'),
            Events::exposure(eventId: 'evt-2', experiment: 'checkout', subjectId: 'u2', experimentRevision: 'rev-1'),
            Events::exposure(eventId: 'evt-3', experiment: 'checkout', subjectId: 'u1', experimentRevision: 'rev-2'),
            Events::exposure(eventId: 'evt-4', experiment: 'pricing', subjectId: 'u1', experimentRevision: 'rev-1'),
        ];

        foreach ($events as $event) {
            $this->tracker->trackExposure($event);
        }

        Assert::same($this->delegate->events, $events);
    }

    public function deduplicatesWhenRevisionIsUnknown(): void
    {
        // The builder defaults experimentRevision to null, which is the case
        // under test: a missing revision must still produce one stable key.
        $first = Events::exposure(eventId: 'evt-1');
        $second = Events::exposure(eventId: 'evt-2');

        $this->tracker->trackExposure($first);
        $this->tracker->trackExposure($second);

        Assert::same($this->delegate->events, [$first]);
    }

    public function resetStartsNewRequestScope(): void
    {
        $event = Events::exposure();

        $this->tracker->trackExposure($event);
        $this->tracker->reset();
        $this->tracker->trackExposure($event);

        Assert::same($this->delegate->events, [$event, $event]);
    }

    public function flushPropagatesToFlushableDelegate(): void
    {
        $this->tracker->flush();

        Assert::same($this->delegate->flushes, 1);
    }

    public function flushIsSkippedForDelegateThatCannotFlush(): void
    {
        (new DeduplicatingExposureTracker(tracker: new NullExposureTracker()))->flush();

        Assert::true(true);
    }
}
