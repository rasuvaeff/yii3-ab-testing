<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface AssignmentStrategy
{
    /**
     * @param array<string, int<0, max>> $variants sorted by key, name => weight
     */
    public function assign(string $salt, string $subjectId, array $variants): string;
}
