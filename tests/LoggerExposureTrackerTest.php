<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Psr\Log\LogLevel;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Test\Support\Log\SimpleLogger;

#[Test]
#[Covers(LoggerExposureTracker::class)]
final class LoggerExposureTrackerTest
{
    private SimpleLogger $logger;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->logger = new SimpleLogger();
    }

    public function logsTheCanonicalRowUnderTheEventKey(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);

        $tracker->trackExposure(Events::exposure(
            eventId: 'evt-1',
            experiment: 'checkout_button',
            variant: 'green',
            subjectId: 'user-1',
            reason: DecisionReason::FallbackTargetingMismatch,
            source: AssignmentSource::Store,
            experimentRevision: 'db:7',
            environment: 'production',
            dimensions: ['plan' => 'pro', 'country' => 'RU'],
        ));

        Assert::same($this->logger->getMessages(), [
            [
                'level' => LogLevel::INFO,
                'message' => 'A/B test exposure',
                'context' => [
                    'event' => [
                        'v' => 2,
                        'event_id' => 'evt-1',
                        'occurred_at' => '2026-08-01 10:00:00.123',
                        'experiment' => 'checkout_button',
                        'variant' => 'green',
                        'subject_id' => 'user-1',
                        'decision_reason' => 'fallback_targeting_mismatch',
                        'assignment_source' => 'store',
                        'experiment_revision' => 'db:7',
                        'environment' => 'production',
                        'dimensions' => '{"country":"RU","plan":"pro"}',
                    ],
                ],
            ],
        ]);
    }

    public function logsAtConfiguredLevel(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger, level: LogLevel::DEBUG);

        $tracker->trackExposure(Events::exposure());

        Assert::same($this->logger->getMessages()[0]['level'], LogLevel::DEBUG);
    }

    public function everyExposureProducesOneRecord(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);

        $tracker->trackExposure(Events::exposure(eventId: 'evt-1'));
        $tracker->trackExposure(Events::exposure(eventId: 'evt-2'));

        Assert::same(\count($this->logger->getMessages()), 2);
    }
}
