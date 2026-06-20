<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class AndTargetingRule implements TargetingRule
{
    /**
     * @param list<TargetingRule> $rules
     */
    public function __construct(
        private array $rules,
    ) {
        if ($rules === []) {
            throw new \InvalidArgumentException('Rules list must not be empty');
        }
    }

    #[\Override]
    public function matches(AssignmentContext $context): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->matches($context)) {
                return false;
            }
        }

        return true;
    }
}
