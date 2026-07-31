<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface AssignmentResolver
{
    public function resolve(
        string $experiment,
        string $subjectId,
        ?string $forcedVariant = null,
        ?AssignmentContext $context = null,
    ): Assignment;
}
