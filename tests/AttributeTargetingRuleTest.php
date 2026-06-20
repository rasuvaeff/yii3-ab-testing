<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;

#[CoversClass(AttributeTargetingRule::class)]
final class AttributeTargetingRuleTest extends TestCase
{
    #[Test]
    public function matchesWhenStringAttributeEquals(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'plan', value: 'pro');
        $context = (new AssignmentContext())->withAttribute(name: 'plan', value: 'pro');

        $this->assertTrue($rule->matches($context));
    }

    #[Test]
    public function matchesWhenBoolAttributeEquals(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'beta', value: true);
        $context = (new AssignmentContext())->withAttribute(name: 'beta', value: true);

        $this->assertTrue($rule->matches($context));
    }

    #[Test]
    public function doesNotMatchWhenAttributeAbsent(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'plan', value: 'pro');
        $context = AssignmentContext::empty();

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function usesStrictTypeComparison(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'flag', value: true);
        $context = (new AssignmentContext())->withAttribute(name: 'flag', value: 1);

        $this->assertFalse($rule->matches($context));
    }
}
