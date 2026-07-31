<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class ConfigExperimentProvider implements ExperimentProvider
{
    private TargetingRuleCodecRegistry $targetingCodecs;

    /**
     * @param array<string, array{enabled?: bool, salt?: string, fallbackVariant?: string, variants?: array<string, int<0, max>>, targeting?: array<string, mixed>|null, configurationId?: string}> $config
     */
    public function __construct(
        private array $config = [],
        ?TargetingRuleCodecRegistry $targetingCodecs = null,
    ) {
        $this->targetingCodecs = $targetingCodecs ?? new TargetingRuleCodecRegistry();
    }

    #[\Override]
    public function getExperiments(): array
    {
        $result = [];

        foreach ($this->config as $name => $data) {
            $targeting = $data['targeting'] ?? null;
            $definition = [
                'enabled' => $data['enabled'] ?? true,
                'salt' => $data['salt'] ?? $name,
                'fallbackVariant' => $data['fallbackVariant'] ?? '',
                'variants' => $data['variants'] ?? [],
                'targeting' => $targeting,
            ];

            $result[$name] = new Experiment(
                name: $name,
                enabled: $definition['enabled'],
                salt: $definition['salt'],
                fallbackVariant: $definition['fallbackVariant'],
                variants: $definition['variants'],
                targeting: $targeting === null ? null : $this->targetingCodecs->decode($targeting),
                configurationId: $data['configurationId'] ?? $this->hashDefinition($definition),
            );
        }

        return $result;
    }

    /** @param array<string, mixed> $definition */
    private function hashDefinition(array $definition): string
    {
        return hash(
            algo: 'sha256',
            data: json_encode(
                value: $this->sortRecursively($definition),
                flags: JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            ),
        );
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->sortRecursively(...), $value);
    }
}
