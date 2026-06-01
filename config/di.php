<?php

declare(strict_types=1);

use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\AssignmentStrategy;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

return [
    AssignmentStrategy::class => WeightedHashAssignmentStrategy::class,
    AbTesting::class => AbTesting::class,
];
