<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Rasuvaeff\Yii3AbTesting\OrTargetingRule;

#[CoversClass(OrTargetingRule::class)]
final class OrTargetingRuleTest extends TestCase
{
    #[Test]
    public function matchesWhenOneRuleMatches(): void
    {
        $rule = new OrTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('staging')
            ->withAttribute(name: 'plan', value: 'pro');

        $this->assertTrue($rule->matches($context));
    }

    #[Test]
    public function matchesWhenFirstRuleMatches(): void
    {
        $rule = new OrTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('production');

        $this->assertTrue($rule->matches($context));
    }

    #[Test]
    public function doesNotMatchWhenNoRuleMatches(): void
    {
        $rule = new OrTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
        ]);
        $context = AssignmentContext::forEnvironment('staging')
            ->withAttribute(name: 'plan', value: 'free');

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function throwsOnEmptyRulesList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrTargetingRule(rules: []);
    }
}
