<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use InvalidArgumentException;

/**
 * @api
 */
final readonly class BuiltInTargetingRuleCodec implements TargetingRuleCodec
{
    private const array TYPES = ['environment', 'attribute', 'and', 'or'];

    #[\Override]
    public function supports(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    #[\Override]
    public function supportsRule(TargetingRule $rule): bool
    {
        return $rule instanceof EnvironmentTargetingRule
            || $rule instanceof AttributeTargetingRule
            || $rule instanceof AndTargetingRule
            || $rule instanceof OrTargetingRule;
    }

    /**
     * @param array<string, mixed> $data
     * @param \Closure(mixed): TargetingRule $decode
     */
    #[\Override]
    public function decode(array $data, \Closure $decode): TargetingRule
    {
        $type = $data['type'] ?? null;

        if (!is_string($type)) {
            throw new InvalidArgumentException('Invalid targeting rule: "type" must be a string');
        }

        return match ($type) {
            'environment' => $this->decodeEnvironment($data),
            'attribute' => $this->decodeAttribute($data),
            'and' => new AndTargetingRule(rules: $this->decodeRules($data, $decode, 'and')),
            'or' => new OrTargetingRule(rules: $this->decodeRules($data, $decode, 'or')),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported built-in targeting rule type "%s"', $type),
            ),
        };
    }

    /**
     * @param \Closure(TargetingRule): array<string, mixed> $encode
     * @return array<string, mixed>
     */
    #[\Override]
    public function encode(TargetingRule $rule, \Closure $encode): array
    {
        return match (true) {
            $rule instanceof EnvironmentTargetingRule => [
                'type' => 'environment',
                'values' => $rule->getEnvironments(),
            ],
            $rule instanceof AttributeTargetingRule => [
                'type' => 'attribute',
                'attribute' => $rule->getAttribute(),
                'value' => $rule->getValue(),
            ],
            $rule instanceof AndTargetingRule => [
                'type' => 'and',
                'rules' => array_map($encode, $rule->getRules()),
            ],
            $rule instanceof OrTargetingRule => [
                'type' => 'or',
                'rules' => array_map($encode, $rule->getRules()),
            ],
            default => throw new InvalidArgumentException(
                sprintf('Unsupported built-in targeting rule class "%s"', $rule::class),
            ),
        };
    }

    /** @param array<string, mixed> $data */
    private function decodeEnvironment(array $data): TargetingRule
    {
        $values = $data['values'] ?? null;

        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new InvalidArgumentException(
                'Invalid "environment" targeting rule: "values" must be a non-empty list of strings',
            );
        }

        foreach ($values as $environment) {
            if (!is_string($environment)) {
                throw new InvalidArgumentException(
                    'Invalid "environment" targeting rule: "values" must be a non-empty list of strings',
                );
            }
        }

        /** @var non-empty-list<string> $values */
        return new EnvironmentTargetingRule(environments: $values);
    }

    /** @param array<string, mixed> $data */
    private function decodeAttribute(array $data): TargetingRule
    {
        $attribute = $data['attribute'] ?? null;
        $value = $data['value'] ?? null;

        if (!is_string($attribute)) {
            throw new InvalidArgumentException(
                'Invalid "attribute" targeting rule: "attribute" must be a string',
            );
        }

        if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
            throw new InvalidArgumentException('Invalid targeting attribute value type');
        }

        return new AttributeTargetingRule(attribute: $attribute, value: $value);
    }

    /**
     * @param array<string, mixed> $data
     * @param \Closure(mixed): TargetingRule $decode
     * @return non-empty-list<TargetingRule>
     */
    private function decodeRules(array $data, \Closure $decode, string $type): array
    {
        $rules = $data['rules'] ?? null;

        if (!is_array($rules) || !array_is_list($rules) || $rules === []) {
            throw new InvalidArgumentException(
                sprintf('Invalid "%s" targeting rule: "rules" must be a non-empty list', $type),
            );
        }

        return array_map($decode, $rules);
    }
}
