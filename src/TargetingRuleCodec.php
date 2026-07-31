<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
interface TargetingRuleCodec
{
    public function supports(string $type): bool;

    public function supportsRule(TargetingRule $rule): bool;

    /**
     * @param array<string, mixed> $data
     * @param \Closure(mixed): TargetingRule $decode
     */
    public function decode(array $data, \Closure $decode): TargetingRule;

    /**
     * @param \Closure(TargetingRule): array<string, mixed> $encode
     * @return array<string, mixed>
     */
    public function encode(TargetingRule $rule, \Closure $encode): array;
}
