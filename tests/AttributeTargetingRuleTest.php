<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(AttributeTargetingRule::class)]
final class AttributeTargetingRuleTest
{
    public function matchesWhenStringAttributeEquals(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'plan', value: 'pro');
        $context = (new AssignmentContext())->withAttribute(name: 'plan', value: 'pro');

        Assert::true($rule->matches($context));
    }

    public function matchesWhenBoolAttributeEquals(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'beta', value: true);
        $context = (new AssignmentContext())->withAttribute(name: 'beta', value: true);

        Assert::true($rule->matches($context));
    }

    public function doesNotMatchWhenAttributeAbsent(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'plan', value: 'pro');
        $context = AssignmentContext::empty();

        Assert::false($rule->matches($context));
    }

    public function usesStrictTypeComparison(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'flag', value: true);
        $context = (new AssignmentContext())->withAttribute(name: 'flag', value: 1);

        Assert::false($rule->matches($context));
    }
}
