<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(WeightedHashAssignmentStrategy::class)]
final class WeightedHashAssignmentStrategyTest
{
    private WeightedHashAssignmentStrategy $strategy;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->strategy = new WeightedHashAssignmentStrategy();
    }

    public function sameSubjectIdGetsSameVariant(): void
    {
        $variants = ['control' => 50, 'green' => 50];

        $result1 = $this->strategy->assign(salt: 'test', subjectId: 'user-1', variants: $variants);
        $result2 = $this->strategy->assign(salt: 'test', subjectId: 'user-1', variants: $variants);

        Assert::same($result1, $result2);
    }

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

        Assert::true($differences > 0);
    }

    public function singleVariantAlwaysAssigned(): void
    {
        $result = $this->strategy->assign(salt: 'test', subjectId: 'any-user', variants: ['only' => 100]);

        Assert::same($result, 'only');
    }

    public function weightedDistributionAcrossSubjects(): void
    {
        $variants = ['control' => 50, 'green' => 50];
        $counts = ['control' => 0, 'green' => 0];

        for ($i = 1; $i <= 1000; ++$i) {
            $variant = $this->strategy->assign(salt: 'test', subjectId: (string) $i, variants: $variants);
            ++$counts[$variant];
        }

        Assert::true($counts['control'] > 300);
        Assert::true($counts['green'] > 300);
    }

    public function sortsVariantsByKeyDeterministically(): void
    {
        $resultZ = $this->strategy->assign(salt: 'sort-test', subjectId: '1', variants: ['z' => 50, 'a' => 50]);
        $resultA = $this->strategy->assign(salt: 'sort-test', subjectId: '1', variants: ['a' => 50, 'z' => 50]);

        Assert::same($resultZ, $resultA);
    }

    public function cumulativeWeightAccumulatesAcrossVariants(): void
    {
        $result = $this->strategy->assign(
            salt: 'mid',
            subjectId: '2',
            variants: ['a' => 10, 'b' => 10, 'c' => 10],
        );

        Assert::same($result, 'b');
    }

    public function cumulativeWeightBoundaryIsCorrect(): void
    {
        $digest = hash('sha256', 'boundary:1');
        $hash = (int) hexdec(substr($digest, 0, 8));
        $bucket = $hash % 100;

        Assert::same($bucket, 37);

        $result = $this->strategy->assign(
            salt: 'boundary',
            subjectId: '1',
            variants: ['a' => 38, 'b' => 62],
        );

        Assert::same($result, 'a');
    }

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

        Assert::same($result, $expected);
    }

    public function hashUsesExactlyEightHexChars(): void
    {
        $digest = hash('sha256', 'test:1');
        $h8 = (int) hexdec(substr($digest, 0, 8));
        $bucket = $h8 % 100;
        $expected = $bucket < 50 ? 'a' : 'b';

        $result = $this->strategy->assign(salt: 'test', subjectId: '1', variants: ['a' => 50, 'b' => 50]);

        Assert::same($result, $expected);
    }

    public function allWeightGoesToOneVariant(): void
    {
        $result = $this->strategy->assign(salt: 'test', subjectId: 'user-1', variants: ['a' => 0, 'b' => 100]);

        Assert::same($result, 'b');
    }

    public function zeroWeightVariantIsSkipped(): void
    {
        $result = $this->strategy->assign(salt: 'zero', subjectId: '1', variants: ['a' => 0, 'b' => 100]);

        Assert::same($result, 'b');
    }

    public function throwsOnZeroTotalWeight(): void
    {
        try {
            $this->strategy->assign(salt: 'test', subjectId: 'u1', variants: ['a' => 0, 'b' => 0]);
            Assert::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Total variant weight must be greater than 0');
        }
    }

    public function weightedDistributionWithUnequalWeights(): void
    {
        $variants = ['control' => 90, 'experiment' => 10];
        $counts = ['control' => 0, 'experiment' => 0];

        for ($i = 1; $i <= 1000; ++$i) {
            $variant = $this->strategy->assign(salt: 'test', subjectId: (string) $i, variants: $variants);
            ++$counts[$variant];
        }

        Assert::true($counts['control'] > 700);
        Assert::true($counts['experiment'] < 300);
    }

    #[Property(runs: 400)]
    public function assignAlwaysReturnsOneOfTheVariants(string $salt, string $subjectId, int $w1, int $w2, int $w3): void
    {
        $variants = ['control' => $w1, 'green' => $w2, 'blue' => $w3];

        Assert::true(\array_key_exists(
            $this->strategy->assign(salt: $salt, subjectId: $subjectId, variants: $variants),
            $variants,
        ));
    }

    /** @return array<string, ArbitraryInterface> */
    private function assignAlwaysReturnsOneOfTheVariantsGenerators(): array
    {
        return [
            'salt' => Gen::stringAscii(),
            'subjectId' => Gen::stringAscii(),
            'w1' => Gen::intBetween(1, 100),
            'w2' => Gen::intBetween(0, 100),
            'w3' => Gen::intBetween(0, 100),
        ];
    }

    #[Property(runs: 400)]
    public function assignIsDeterministic(string $salt, string $subjectId, int $w1, int $w2): void
    {
        $variants = ['a' => $w1, 'b' => $w2];

        Assert::same(
            $this->strategy->assign(salt: $salt, subjectId: $subjectId, variants: $variants),
            $this->strategy->assign(salt: $salt, subjectId: $subjectId, variants: $variants),
        );
    }

    /** @return array<string, ArbitraryInterface> */
    private function assignIsDeterministicGenerators(): array
    {
        return [
            'salt' => Gen::stringAscii(),
            'subjectId' => Gen::stringAscii(),
            'w1' => Gen::intBetween(1, 100),
            'w2' => Gen::intBetween(0, 100),
        ];
    }

    #[Property(runs: 400)]
    public function zeroWeightVariantIsNeverAssigned(string $salt, string $subjectId, int $w1, int $w2): void
    {
        $variants = ['a' => 0, 'b' => $w1, 'c' => $w2];

        Assert::true($this->strategy->assign(salt: $salt, subjectId: $subjectId, variants: $variants) !== 'a');
    }

    /** @return array<string, ArbitraryInterface> */
    private function zeroWeightVariantIsNeverAssignedGenerators(): array
    {
        return [
            'salt' => Gen::stringAscii(),
            'subjectId' => Gen::stringAscii(),
            'w1' => Gen::intBetween(1, 100),
            'w2' => Gen::intBetween(0, 100),
        ];
    }

    #[Property(runs: 300)]
    public function singleVariantIsAlwaysAssigned(string $salt, string $subjectId, int $weight): void
    {
        Assert::same(
            $this->strategy->assign(salt: $salt, subjectId: $subjectId, variants: ['only' => $weight]),
            'only',
        );
    }

    /** @return array<string, ArbitraryInterface> */
    private function singleVariantIsAlwaysAssignedGenerators(): array
    {
        return [
            'salt' => Gen::stringAscii(),
            'subjectId' => Gen::stringAscii(),
            'weight' => Gen::intBetween(1, 100),
        ];
    }
}
