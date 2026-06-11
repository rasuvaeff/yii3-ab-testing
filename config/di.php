<?php

declare(strict_types=1);

use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\AssignmentStrategy;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

return [
    AssignmentStrategy::class => WeightedHashAssignmentStrategy::class,
    AbTesting::class => [
        'class' => AbTesting::class,
        // Worker runtimes (RoadRunner, Swoole) reset state between requests via
        // yiisoft/di StateResetter; without this the experiment set — including
        // the enabled kill switch — would be frozen for the worker's lifetime.
        'reset' => function (): void {
            /** @var AbTesting $this */
            $this->getRegistry()->reset();
        },
    ],
];
