<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Psr\Log\AbstractLogger;
use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\AllowListAnalyticsContextPolicy;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\LoggerConversionTracker;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

/**
 * The log-shipping delivery path: the application writes the canonical schema
 * v2 row to its log, and a collector (Vector, Fluent Bit, Kafka) loads it into
 * the same ClickHouse tables the durable outbox path writes.
 *
 * Nothing here touches the network during the request, and there is no buffer
 * to lose when a worker dies. See examples/vector.toml for the collector side.
 */
$logger = new class extends AbstractLogger {
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        echo json_encode(
            ['level' => $level, 'message' => $message] + $context,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n";
    }
};

$ab = new AbTesting(
    provider: new ConfigExperimentProvider(config: [
        'checkout-button' => [
            'enabled' => true,
            'salt' => 'checkout-v1',
            'fallbackVariant' => 'control',
            'variants' => ['control' => 50, 'green' => 50],
        ],
    ]),
    strategy: new WeightedHashAssignmentStrategy(),
    exposureTracker: new LoggerExposureTracker(logger: $logger),
    conversionTracker: new LoggerConversionTracker(logger: $logger),
    // Only listed attributes become analytics dimensions. Anything else in the
    // context is dropped, so adding an attribute for targeting cannot leak it
    // into storage by accident.
    contextPolicy: new AllowListAnalyticsContextPolicy(allowedAttributes: ['country']),
);

$context = AssignmentContext::forEnvironment('production')
    ->withAttribute('country', 'RU')
    ->withAttribute('email', 'never@leaves.local');

$assignment = $ab->assign(experiment: 'checkout-button', subjectId: 'visitor-42', context: $context);

$exposure = $ab->trackExposure($assignment);
$ab->trackConversion($assignment, goal: 'purchase', exposure: $exposure);

echo "\nNote the `event` key: one flat object per record, every value scalar,\n";
echo "`dimensions` a JSON string. That shape is what the collector maps to\n";
echo "columns, and `email` is absent because the policy did not allow it.\n";
