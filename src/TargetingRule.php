<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface TargetingRule
{
    public function matches(AssignmentContext $context): bool;
}
