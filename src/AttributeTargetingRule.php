<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class AttributeTargetingRule implements TargetingRule
{
    public function __construct(
        private string $attribute,
        private string|int|float|bool $value,
    ) {}

    #[\Override]
    public function matches(AssignmentContext $context): bool
    {
        return $context->getAttribute($this->attribute) === $this->value;
    }
}
