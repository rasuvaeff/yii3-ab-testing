<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;

#[CoversClass(EnvironmentTargetingRule::class)]
final class EnvironmentTargetingRuleTest extends TestCase
{
    #[Test]
    public function matchesWhenEnvironmentInList(): void
    {
        $rule = new EnvironmentTargetingRule(environments: ['production', 'staging']);
        $context = AssignmentContext::forEnvironment('production');

        $this->assertTrue($rule->matches($context));
    }

    #[Test]
    public function doesNotMatchWhenEnvironmentNotInList(): void
    {
        $rule = new EnvironmentTargetingRule(environments: ['production']);
        $context = AssignmentContext::forEnvironment('staging');

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function doesNotMatchWhenEnvironmentIsNull(): void
    {
        $rule = new EnvironmentTargetingRule(environments: ['production']);
        $context = AssignmentContext::empty();

        $this->assertFalse($rule->matches($context));
    }

    #[Test]
    public function throwsOnEmptyEnvironmentsList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EnvironmentTargetingRule(environments: []);
    }
}
