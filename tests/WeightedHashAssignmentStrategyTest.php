<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

#[CoversClass(WeightedHashAssignmentStrategy::class)]
final class WeightedHashAssignmentStrategyTest extends TestCase
{
    private WeightedHashAssignmentStrategy $strategy;

    #[\Override]
    protected function setUp(): void
    {
        $this->strategy = new WeightedHashAssignmentStrategy();
    }

    #[Test]
    public function sameSubjectIdGetsSameVariant(): void
    {
        $variants = ['control' => 50, 'green' => 50];

        $result1 = $this->strategy->assign(salt: 'test', subjectId: 'user-1', variants: $variants);
        $result2 = $this->strategy->assign(salt: 'test', subjectId: 'user-1', variants: $variants);

        $this->assertSame($result1, $result2);
    }

    #[Test]
    public function differentSaltGivesIndependentDistribution(): void
    {
        $variants = ['a' => 50, 'b' => 50];
        $differences = 0;

        for ($i = 1; $i <= 100; ++$i) {
            $r1 = $this->strategy->assign(salt: 'salt-1', subjectId: (string) $i, variants: $variants);
            $r2 = $this->strategy->assign(salt: 'salt-2', subjectId: (string) $i, variants: $variants);

            if ($r1 !== $r2) {
                ++$differences;
            }
        }

        $this->assertGreaterThan(0, $differences);
    }

    #[Test]
    public function singleVariantAlwaysAssigned(): void
    {
        $result = $this->strategy->assign(salt: 'test', subjectId: 'any-user', variants: ['only' => 100]);

        $this->assertSame('only', $result);
    }

    #[Test]
    public function weightedDistributionAcrossSubjects(): void
    {
        $variants = ['control' => 50, 'green' => 50];
        $counts = ['control' => 0, 'green' => 0];

        for ($i = 1; $i <= 1000; ++$i) {
            $variant = $this->strategy->assign(salt: 'test', subjectId: (string) $i, variants: $variants);
            ++$counts[$variant];
        }

        $this->assertGreaterThan(300, $counts['control']);
        $this->assertGreaterThan(300, $counts['green']);
    }

    #[Test]
    public function sortsVariantsByKeyDeterministically(): void
    {
        $resultZ = $this->strategy->assign(salt: 'sort-test', subjectId: '1', variants: ['z' => 50, 'a' => 50]);
        $resultA = $this->strategy->assign(salt: 'sort-test', subjectId: '1', variants: ['a' => 50, 'z' => 50]);

        $this->assertSame($resultZ, $resultA);
    }

    #[Test]
    public function cumulativeWeightAccumulatesAcrossVariants(): void
    {
        // salt 'mid', subject '2' → bucket 13 of 30 → middle variant 'b'.
        // A non-accumulating bug ($cumulative = $weight) would fall through to 'c'.
        $result = $this->strategy->assign(
            salt: 'mid',
            subjectId: '2',
            variants: ['a' => 10, 'b' => 10, 'c' => 10],
        );

        $this->assertSame('b', $result);
    }

    #[Test]
    public function cumulativeWeightBoundaryIsCorrect(): void
    {
        $digest = hash('sha256', 'boundary:1');
        $hash = (int) hexdec(substr($digest, 0, 8));
        $bucket = $hash % 100;

        // bucket = 37, so variant 'a' needs weight > 37
        $this->assertSame(37, $bucket);

        $result = $this->strategy->assign(
            salt: 'boundary',
            subjectId: '1',
            variants: ['a' => 38, 'b' => 62],
        );

        $this->assertSame('a', $result);
    }

    #[Test]
    public function concatenatesSaltWithColonAndSubjectId(): void
    {
        $digest = hash('sha256', 'mysalt:myuser');
        $hash = (int) hexdec(substr($digest, 0, 8));
        $bucket = $hash % 100;
        $expected = $bucket < 50 ? 'a' : 'b';

        $result = $this->strategy->assign(
            salt: 'mysalt',
            subjectId: 'myuser',
            variants: ['a' => 50, 'b' => 50],
        );

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function hashUsesExactlyEightHexChars(): void
    {
        $digest = hash('sha256', 'test:1');
        $h8 = (int) hexdec(substr($digest, 0, 8));
        $bucket = $h8 % 100;
        $expected = $bucket < 50 ? 'a' : 'b';

        $result = $this->strategy->assign(salt: 'test', subjectId: '1', variants: ['a' => 50, 'b' => 50]);

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function allWeightGoesToOneVariant(): void
    {
        $result = $this->strategy->assign(salt: 'test', subjectId: 'user-1', variants: ['a' => 0, 'b' => 100]);

        $this->assertSame('b', $result);
    }

    #[Test]
    public function zeroWeightVariantIsSkipped(): void
    {
        $result = $this->strategy->assign(salt: 'zero', subjectId: '1', variants: ['a' => 0, 'b' => 100]);

        $this->assertSame('b', $result);
    }

    #[Test]
    public function throwsOnZeroTotalWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total variant weight must be greater than 0');

        $this->strategy->assign(salt: 'test', subjectId: 'u1', variants: ['a' => 0, 'b' => 0]);
    }

    #[Test]
    public function weightedDistributionWithUnequalWeights(): void
    {
        $variants = ['control' => 90, 'experiment' => 10];
        $counts = ['control' => 0, 'experiment' => 0];

        for ($i = 1; $i <= 1000; ++$i) {
            $variant = $this->strategy->assign(salt: 'test', subjectId: (string) $i, variants: $variants);
            ++$counts[$variant];
        }

        $this->assertGreaterThan(700, $counts['control']);
        $this->assertLessThan(300, $counts['experiment']);
    }
}
