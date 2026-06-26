<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Psr\Log\LogLevel;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;
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

    public function logsExposureAtInfoLevelWithFullContext(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);
        $assignment = new Assignment(experiment: 'checkout-button', variant: 'green', subjectId: 'user-1');

        $tracker->trackExposure($assignment);

        Assert::same($this->logger->getMessages(), [
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
        ]);
    }

    public function logsAtConfiguredLevel(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger, level: LogLevel::DEBUG);

        $tracker->trackExposure(new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1'));

        Assert::same($this->logger->getMessages()[0]['level'], LogLevel::DEBUG);
    }

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
        Assert::true($context['isForced']);
        Assert::true($context['isFallback']);
    }

    public function carriesStickyFlag(): void
    {
        $tracker = new LoggerExposureTracker(logger: $this->logger);

        $tracker->trackExposure(new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            isSticky: true,
        ));

        Assert::true($this->logger->getMessages()[0]['context']['isSticky']);
    }

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
        Assert::same($logged['environment'], 'production');
        Assert::same($logged['attributes'], ['country' => 'DE']);
    }
}
