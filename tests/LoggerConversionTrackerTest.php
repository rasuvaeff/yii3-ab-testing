<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Psr\Log\LogLevel;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\LoggerConversionTracker;
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

    public function logsConversionAtInfoLevelWithFullContext(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);
        $assignment = new Assignment(experiment: 'checkout-button', variant: 'green', subjectId: 'user-1');

        $tracker->trackConversion($assignment, goal: 'purchase');

        Assert::same($this->logger->getMessages(), [
            [
                'level' => LogLevel::INFO,
                'message' => 'A/B test conversion',
                'context' => [
                    'experiment' => 'checkout-button',
                    'variant' => 'green',
                    'subjectId' => 'user-1',
                    'goal' => 'purchase',
                    'isForced' => false,
                    'isFallback' => false,
                    'isSticky' => false,
                    'environment' => null,
                    'attributes' => [],
                ],
            ],
        ]);
    }

    public function logsAtConfiguredLevel(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger, level: LogLevel::NOTICE);

        $tracker->trackConversion(new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1'), goal: 'signup');

        Assert::same($this->logger->getMessages()[0]['level'], LogLevel::NOTICE);
    }

    public function recordsGoal(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);

        $tracker->trackConversion(new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1'), goal: 'add-to-cart');

        Assert::same($this->logger->getMessages()[0]['context']['goal'], 'add-to-cart');
    }

    public function carriesStickyFlag(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);

        $tracker->trackConversion(
            new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', isSticky: true),
            goal: 'purchase',
        );

        Assert::true($this->logger->getMessages()[0]['context']['isSticky']);
    }

    public function carriesEnvironmentAndAttributesFromContext(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);
        $context = AssignmentContext::forEnvironment('staging')->withAttribute('plan', 'pro');

        $tracker->trackConversion(
            new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', context: $context),
            goal: 'purchase',
        );

        $logged = $this->logger->getMessages()[0]['context'];
        Assert::same($logged['environment'], 'staging');
        Assert::same($logged['attributes'], ['plan' => 'pro']);
    }
}
