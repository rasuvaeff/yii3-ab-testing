<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class ExperimentRegistry
{
    /** @var array<string, Experiment> */
    private array $experiments;

    public function __construct(
        ExperimentProvider $provider,
    ) {
        $this->experiments = $provider->getExperiments();
    }

    public function get(string $name): Experiment
    {
        if (!isset($this->experiments[$name])) {
            throw new Exception\InvalidExperimentException(
                message: sprintf('Unknown experiment "%s"', $name),
            );
        }

        return $this->experiments[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->experiments[$name]);
    }

    /**
     * @return array<string, Experiment>
     */
    public function all(): array
    {
        return $this->experiments;
    }
}
