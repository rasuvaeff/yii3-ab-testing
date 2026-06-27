<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Rasuvaeff\Yii3AbTesting\OrTargetingRule;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(OrTargetingRule::class)]
final class OrTargetingRuleTest
{
    public function matchesWhenOneRuleMatches(): void
    {
        $rule = new OrTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('staging')
            ->withAttribute(name: 'plan', value: 'pro');

        Assert::true($rule->matches($context));
    }

    public function matchesWhenFirstRuleMatches(): void
    {
        $rule = new OrTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('production');

        Assert::true($rule->matches($context));
    }

    public function doesNotMatchWhenNoRuleMatches(): void
    {
        $rule = new OrTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('staging')
            ->withAttribute(name: 'plan', value: 'free');

        Assert::false($rule->matches($context));
    }

    public function throwsOnEmptyRulesList(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new OrTargetingRule(rules: []);
    }
}
