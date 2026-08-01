<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
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

    public function defaultsToComputedAssignment(): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1');

        Assert::same($a->reason, DecisionReason::Assigned);
        Assert::same($a->source, AssignmentSource::Computed);
        Assert::false($a->isForced());
        Assert::false($a->isFallback());
        Assert::false($a->isSticky());
        Assert::false($a->isTargetingMismatch());
    }

    /**
     * @param list<string> $expectedTrue
     */
    #[DataProvider('reasonProvider')]
    public function derivedChecksFollowTheReason(DecisionReason $reason, array $expectedTrue): void
    {
        $a = new Assignment(experiment: 'exp', variant: 'a', subjectId: 'u1', reason: $reason);

        Assert::same(
            [
                'forced' => $a->isForced(),
                'fallback' => $a->isFallback(),
                'targetingMismatch' => $a->isTargetingMismatch(),
            ],
            [
                'forced' => \in_array('forced', $expectedTrue, true),
                'fallback' => \in_array('fallback', $expectedTrue, true),
                'targetingMismatch' => \in_array('targetingMismatch', $expectedTrue, true),
            ],
        );
    }

    public static function reasonProvider(): iterable
    {
        yield 'assigned' => [DecisionReason::Assigned, []];
        yield 'forced' => [DecisionReason::Forced, ['forced']];
        yield 'disabled' => [DecisionReason::FallbackDisabled, ['fallback']];
        yield 'targeting mismatch' => [
            DecisionReason::FallbackTargetingMismatch,
            ['fallback', 'targetingMismatch'],
        ];
    }

    public function stickySourceIsReported(): void
    {
        $a = new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            source: AssignmentSource::Store,
        );

        Assert::true($a->isSticky());
    }

    public function stickyForcedRemainsRepresentable(): void
    {
        $a = new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            reason: DecisionReason::Forced,
            source: AssignmentSource::Store,
        );

        Assert::true($a->isForced());
        Assert::true($a->isSticky());
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

    public function configurationIdIsStored(): void
    {
        $a = new Assignment(
            experiment: 'exp',
            variant: 'a',
            subjectId: 'u1',
            configurationId: 'revision-42',
        );

        Assert::same($a->configurationId, 'revision-42');
    }
}
