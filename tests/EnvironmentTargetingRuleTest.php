<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(EnvironmentTargetingRule::class)]
final class EnvironmentTargetingRuleTest
{
    public function matchesWhenEnvironmentInList(): void
    {
        $rule = new EnvironmentTargetingRule(environments: ['production', 'staging']);
        $context = AssignmentContext::forEnvironment('production');

        Assert::true($rule->matches($context));
    }

    public function doesNotMatchWhenEnvironmentNotInList(): void
    {
        $rule = new EnvironmentTargetingRule(environments: ['production']);
        $context = AssignmentContext::forEnvironment('staging');

        Assert::false($rule->matches($context));
    }

    public function doesNotMatchWhenEnvironmentIsNull(): void
    {
        $rule = new EnvironmentTargetingRule(environments: ['production']);
        $context = AssignmentContext::empty();

        Assert::false($rule->matches($context));
    }

    public function throwsOnEmptyEnvironmentsList(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new EnvironmentTargetingRule(environments: []);
    }
}
