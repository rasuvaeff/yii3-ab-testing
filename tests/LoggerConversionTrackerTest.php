<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\LoggerConversionTracker;
use Yiisoft\Test\Support\Log\SimpleLogger;

#[CoversClass(LoggerConversionTracker::class)]
final class LoggerConversionTrackerTest extends TestCase
{
    private SimpleLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = new SimpleLogger();
    }

    #[Test]
    public function logsConversionAtInfoLevelWithFullContext(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);
        $assignment = new Assignment(experiment: 'checkout-button', variant: 'green', subjectId: 'user-1');

        $tracker->trackConversion($assignment, goal: 'purchase');

        $this->assertSame([
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
        ], $this->logger->getMessages());
    }

    #[Test]
    public function logsAtConfiguredLevel(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger, level: LogLevel::NOTICE);

        $tracker->trackConversion(new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1'), goal: 'signup');

        $this->assertSame(LogLevel::NOTICE, $this->logger->getMessages()[0]['level']);
    }

    #[Test]
    public function recordsGoal(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);

        $tracker->trackConversion(new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1'), goal: 'add-to-cart');

        $this->assertSame('add-to-cart', $this->logger->getMessages()[0]['context']['goal']);
    }

    #[Test]
    public function carriesStickyFlag(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);

        $tracker->trackConversion(
            new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', isSticky: true),
            goal: 'purchase',
        );

        $this->assertTrue($this->logger->getMessages()[0]['context']['isSticky']);
    }

    #[Test]
    public function carriesEnvironmentAndAttributesFromContext(): void
    {
        $tracker = new LoggerConversionTracker(logger: $this->logger);
        $context = AssignmentContext::forEnvironment('staging')->withAttribute('plan', 'pro');

        $tracker->trackConversion(
            new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', context: $context),
            goal: 'purchase',
        );

        $logged = $this->logger->getMessages()[0]['context'];
        $this->assertSame('staging', $logged['environment']);
        $this->assertSame(['plan' => 'pro'], $logged['attributes']);
    }
}
