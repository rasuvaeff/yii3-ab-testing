<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\AndTargetingRule;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;

#[CoversClass(AndTargetingRule::class)]
final class AndTargetingRuleTest extends TestCase
{
    #[Test]
    public function matchesWhenAllRulesMatch(): void
    {
        $rule = new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('production')
            ->withAttribute(name: 'plan', value: 'pro');

        $this->assertTrue($rule->matches($context));
    }

    #[Test]
    public function doesNotMatchWhenFirstRuleFails(): void
    {
        $rule = new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('staging')
            ->withAttribute(name: 'plan', value: 'pro');

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function doesNotMatchWhenLastRuleFails(): void
    {
        $rule = new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('production')
            ->withAttribute(name: 'plan', value: 'free');

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function throwsOnEmptyRulesList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AndTargetingRule(rules: []);
    }
}
