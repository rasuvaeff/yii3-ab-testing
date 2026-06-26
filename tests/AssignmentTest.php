<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Assignment::class)]
final class AssignmentTest
{
    public function isVariantReturnsTrueForMatch(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'user-1');

        Assert::true($a->isVariant('green'));
    }

    public function isVariantReturnsFalseForMismatch(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'green', subjectId: 'user-1');

        Assert::false($a->isVariant('control'));
    }

    public function defaultFlagsAreFalse(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        Assert::false($a->isForced);
        Assert::false($a->isFallback);
        Assert::false($a->isSticky);
        Assert::false($a->isTargetingMismatch);
    }

    public function targetingMismatchFlagIsSet(): void
    {
        $a = new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            isTargetingMismatch: true,
        );

        Assert::true($a->isTargetingMismatch);
    }

    public function stickyFlagIsSet(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', isSticky: true);

        Assert::true($a->isSticky);
    }

    public function forcedFlagIsSet(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', isForced: true);

        Assert::true($a->isForced);
    }

    public function fallbackFlagIsSet(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', isFallback: true);

        Assert::true($a->isFallback);
    }

    public function contextDefaultsToNull(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        Assert::null($a->context);
    }

    public function contextIsStored(): void
    {
        $context = AssignmentContext::forEnvironment('production');
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', context: $context);

        Assert::same($a->context, $context);
    }
}
