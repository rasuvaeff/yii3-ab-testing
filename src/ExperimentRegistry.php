<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Lazy view over an {@see ExperimentProvider}: the provider is queried on first
 * access, then the set is memoized for the registry's lifetime. {@see reset()}
 * drops the memo so long-running runtimes (RoadRunner, Swoole) can re-read the
 * provider per request — otherwise a kill switch flipped in the source would not
 * take effect until the worker restarts.
 *
 * @api
 */
final class ExperimentRegistry
{
    /** @var array<string, Experiment>|null */
    private ?array $experiments = null;

    public function __construct(
        private readonly ExperimentProvider $provider,
    ) {}

    public function get(string $name): Experiment
    {
        $experiments = $this->load();

        if (!isset($experiments[$name])) {
            throw new Exception\InvalidExperimentException(
                message: sprintf('Unknown experiment "%s"', $name),
            );
        }

        return $experiments[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->load()[$name]);
    }

    /**
     * @return array<string, Experiment>
     */
    public function all(): array
    {
        return $this->load();
    }

    public function reset(): void
    {
        $this->experiments = null;
    }

    /**
     * @return array<string, Experiment>
     */
    private function load(): array
    {
        return $this->experiments ??= $this->provider->getExperiments();
    }
}
