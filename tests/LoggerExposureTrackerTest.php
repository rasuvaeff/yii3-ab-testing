<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;
use Yiisoft\Test\Support\Log\SimpleLogger;

#[CoversClass(LoggerExposureTracker::class)]
final class LoggerExposureTrackerTest extends TestCase
{
    private SimpleLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->logger = new SimpleLogger();
    }

    #[Test]
    public function logsExposureAtInfoLevelWithFullContext(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);
        $assignment = new Assignment(experiment: 'checkout-button', variant: 'green', subjectId: 'user-1');

        $tracker->trackExposure($assignment);

        $this->assertSame([
            [
                'level' => LogLevel::INFO,
                'message' => 'A/B test exposure',
                'context' => [
                    'experiment' => 'checkout-button',
                    'variant' => 'green',
                    'subjectId' => 'user-1',
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
        $tracker = new LoggerExposureTracker(logger: $this->logger, level: LogLevel::DEBUG);

        $tracker->trackExposure(new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1'));

        $this->assertSame(LogLevel::DEBUG, $this->logger->getMessages()[0]['level']);
    }

    #[Test]
    public function carriesForcedAndFallbackFlags(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);

        $tracker->trackExposure(new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            isForced: true,
            isFallback: true,
        ));

        $context = $this->logger->getMessages()[0]['context'];
        $this->assertTrue($context['isForced']);
        $this->assertTrue($context['isFallback']);
    }

    #[Test]
    public function carriesStickyFlag(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);

        $tracker->trackExposure(new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            isSticky: true,
        ));

        $this->assertTrue($this->logger->getMessages()[0]['context']['isSticky']);
    }

    #[Test]
    public function carriesEnvironmentAndAttributesFromContext(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);
        $context = AssignmentContext::forEnvironment('production')->withAttribute('country', 'DE');

        $tracker->trackExposure(new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            context: $context,
        ));

        $logged = $this->logger->getMessages()[0]['context'];
        $this->assertSame('production', $logged['environment']);
        $this->assertSame(['country' => 'DE'], $logged['attributes']);
    }
}
