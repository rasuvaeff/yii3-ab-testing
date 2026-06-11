<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface AssignmentStrategy
{
    /**
     * Picks a variant for the subject. Callers must pass at least one variant
     * with a total weight greater than 0 ({@see Experiment} guarantees this);
     * implementations throw {@see \InvalidArgumentException} otherwise.
     *
     * @param array<string, int<0, max>> $variants name => weight
     */
    public function assign(string $salt, string $subjectId, array $variants): string;
}
