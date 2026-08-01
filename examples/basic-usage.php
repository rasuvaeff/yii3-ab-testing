<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

$provider = new ConfigExperimentProvider(config: [
    'checkout-button' => [
        'enabled' => true,
        'salt' => 'checkout-v1',
        'fallbackVariant' => 'control',
        'variants' => ['control' => 50, 'green' => 50],
        'targeting' => [
            'type' => 'environment',
            'values' => ['production'],
        ],
    ],
]);

$ab = new AbTesting(
    provider: $provider,
    strategy: new WeightedHashAssignmentStrategy(),
);

echo "A/B Testing Assignment:\n\n";

for ($i = 1; $i <= 10; ++$i) {
    $assignment = $ab->assign(
        experiment: 'checkout-button',
        subjectId: (string) $i,
        context: AssignmentContext::forEnvironment('production'),
    );

    // One canonical reason instead of a set of overlapping booleans: a disabled
    // experiment and a targeting miss are now distinguishable.
    echo sprintf(
        "  user-%d → %s (%s)\n",
        $i,
        $assignment->variant,
        $assignment->reason->value,
    );
}

echo "\nAssignment is pure — nothing was tracked above.\n";
echo "Exposure stays an explicit act, so prefetch and hidden branches\n";
echo "cannot invent impressions:\n\n";

$assignment = $ab->assign(
    experiment: 'checkout-button',
    subjectId: '1',
    context: AssignmentContext::forEnvironment('production'),
);

$exposure = $ab->trackExposure($assignment);

echo sprintf("  exposure %s at %s\n", $exposure->eventId, $exposure->occurredAt->format('c'));

// The receipt travels in a cookie or session, so a conversion in a later
// request is attributed to the variant the visitor actually saw — even if the
// experiment is reweighted in between.
$receipt = $exposure->receipt();
$conversion = $ab->trackConversionForReceipt($receipt, goal: 'purchase');

echo sprintf(
    "  conversion %s for goal \"%s\", linked to exposure %s\n",
    $conversion->eventId,
    $conversion->goal,
    $conversion->exposureEventId,
);

echo "\nBoth events went to the default no-op sink. Bind a tracker to deliver\n";
echo "them: LoggerExposureTracker for log shipping, or the outbox adapter for\n";
echo "durable delivery. See examples/log-shipping.php.\n";
