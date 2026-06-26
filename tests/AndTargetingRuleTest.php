<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AndTargetingRule;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(AndTargetingRule::class)]
final class AndTargetingRuleTest
{
    public function matchesWhenAllRulesMatch(): void
    {
        $rule = new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('production')
            ->withAttribute(name: 'plan', value: 'pro');

        Assert::true($rule->matches($context));
    }

    public function doesNotMatchWhenFirstRuleFails(): void
    {
        $rule = new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('staging')
            ->withAttribute(name: 'plan', value: 'pro');

        Assert::false($rule->matches($context));
    }

    public function doesNotMatchWhenLastRuleFails(): void
    {
        $rule = new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('production')
            ->withAttribute(name: 'plan', value: 'free');

        Assert::false($rule->matches($context));
    }

    public function throwsOnEmptyRulesList(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new AndTargetingRule(rules: []);
    }
}
