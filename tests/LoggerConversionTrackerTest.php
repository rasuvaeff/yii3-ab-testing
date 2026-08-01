<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Psr\Log\LogLevel;
use Rasuvaeff\Yii3AbTesting\LoggerConversionTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\Events;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Test\Support\Log\SimpleLogger;

#[Test]
#[Covers(LoggerConversionTracker::class)]
final class LoggerConversionTrackerTest
{
    private SimpleLogger $logger;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->logger = new SimpleLogger();
    }

    public function logsTheCanonicalRowUnderTheEventKey(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);

        $tracker->trackConversion(Events::conversion(
            eventId: 'evt-2',
            experiment: 'checkout_button',
            variant: 'green',
            subjectId: 'user-1',
            goal: 'purchase',
            experimentRevision: 'db:7',
            environment: 'production',
            dimensions: ['country' => 'RU'],
            exposureEventId: 'evt-1',
        ));

        Assert::same($this->logger->getMessages(), [
            [
                'level' => LogLevel::INFO,
                'message' => 'A/B test conversion',
                'context' => [
                    'event' => [
                        'v' => 2,
                        'event_id' => 'evt-2',
                        'occurred_at' => '2026-08-01 10:00:00.123',
                        'experiment' => 'checkout_button',
                        'variant' => 'green',
                        'subject_id' => 'user-1',
                        'goal' => 'purchase',
                        'decision_reason' => 'assigned',
                        'assignment_source' => 'computed',
                        'experiment_revision' => 'db:7',
                        'environment' => 'production',
                        'dimensions' => '{"country":"RU"}',
                        'exposure_event_id' => 'evt-1',
                    ],
                ],
            ],
        ]);
    }

    public function logsAtConfiguredLevel(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger, level: LogLevel::DEBUG);

        $tracker->trackConversion(Events::conversion());

        Assert::same($this->logger->getMessages()[0]['level'], LogLevel::DEBUG);
    }

    public function everyConversionProducesOneRecord(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);

        $tracker->trackConversion(Events::conversion(eventId: 'evt-1'));
        $tracker->trackConversion(Events::conversion(eventId: 'evt-2'));

        Assert::same(\count($this->logger->getMessages()), 2);
    }
}
