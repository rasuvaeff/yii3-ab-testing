<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

$provider = new ConfigExperimentProvider(config: [
    'checkout-button' => [
        'enabled' => true,
        'salt' => 'checkout-v1',
        'fallbackVariant' => 'control',
        'variants' => ['control' => 50, 'green' => 50],
    ],
]);

$ab = new AbTesting(
    provider: $provider,
    strategy: new WeightedHashAssignmentStrategy(),
);

echo "A/B Testing Assignment:\n\n";

for ($i = 1; $i <= 10; ++$i) {
    $assignment = $ab->assign(experiment: 'checkout-button', subjectId: (string) $i);
    echo sprintf('  user-%d → %s', $i, $assignment->variant);

    if ($assignment->isForced) {
        echo ' (forced)';
    }

    if ($assignment->isFallback) {
        echo ' (fallback)';
    }

    echo "\n";
}
