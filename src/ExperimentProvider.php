<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface ExperimentProvider
{
    /**
     * @return array<string, Experiment>
     */
    public function getExperiments(): array;
}
