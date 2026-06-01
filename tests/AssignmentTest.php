<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;

#[CoversClass(Assignment::class)]
final class AssignmentTest extends TestCase
{
    #[Test]
    public function isVariantReturnsTrueForMatch(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'user-1');

        $this->assertTrue($a->isVariant('green'));
    }

    #[Test]
    public function isVariantReturnsFalseForMismatch(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'user-1');

        $this->assertFalse($a->isVariant('control'));
    }

    #[Test]
    public function defaultFlagsAreFalse(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        $this->assertFalse($a->isForced);
        $this->assertFalse($a->isFallback);
    }

    #[Test]
    public function forcedFlagIsSet(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', isForced: true);

        $this->assertTrue($a->isForced);
    }

    #[Test]
    public function fallbackFlagIsSet(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', isFallback: true);

        $this->assertTrue($a->isFallback);
    }

    #[Test]
    public function contextDefaultsToNull(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        $this->assertNull($a->context);
    }

    #[Test]
    public function contextIsStored(): void
    {
        $context = AssignmentContext::forEnvironment('production');
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', context: $context);

        $this->assertSame($context, $a->context);
    }
}
