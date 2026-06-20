<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class EnvironmentTargetingRule implements TargetingRule
{
    /**
     * @param list<string> $environments
     */
    public function __construct(
        private array $environments,
    ) {
        if ($environments === []) {
            throw new \InvalidArgumentException('Environments list must not be empty');
        }
    }

    #[\Override]
    public function matches(AssignmentContext $context): bool
    {
        return in_array($context->getEnvironment(), $this->environments, strict: true);
    }
}
