<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class ConfigExperimentProvider implements ExperimentProvider
{
    /**
     * @param array<string, array{enabled?: bool, salt?: string, fallbackVariant?: string, variants?: array<string, int<0, max>>}> $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    #[\Override]
    public function getExperiments(): array
    {
        $result = [];

        foreach ($this->config as $name => $data) {
            $result[$name] = new Experiment(
                name: $name,
                enabled: $data['enabled'] ?? true,
                salt: $data['salt'] ?? $name,
                fallbackVariant: $data['fallbackVariant'] ?? '',
                variants: $data['variants'] ?? [],
            );
        }

        return $result;
    }
}
