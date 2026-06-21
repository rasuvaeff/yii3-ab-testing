<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Benchmarks;

use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;
use Testo\Bench;

/**
 * Compares assignment cost for 2 variants vs 5 variants.
 * The strategy sorts by key + iterates cumulative weights, so fan-out matters.
 */
final class VariantAssignmentBench
{
    private static WeightedHashAssignmentStrategy $strategy;

    /** @var array<string, int<0,max>> */
    private static array $variantsTwo = ['control' => 50, 'treatment' => 50];

    /** @var array<string, int<0,max>> */
    private static array $variantsFive = [
        'control' => 40,
        'a' => 15,
        'b' => 15,
        'c' => 15,
        'd' => 15,
    ];

    #[Bench(
        callables: [
            'five-variants' => [self::class, 'assignFiveVariants'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function assignTwoVariants(): string
    {
        return (self::$strategy ??= new WeightedHashAssignmentStrategy())->assign(
            salt: 'checkout-experiment',
            subjectId: 'user-42',
            variants: self::$variantsTwo,
        );
    }

    public static function assignFiveVariants(): string
    {
        return (self::$strategy ??= new WeightedHashAssignmentStrategy())->assign(
            salt: 'checkout-experiment',
            subjectId: 'user-42',
            variants: self::$variantsFive,
        );
    }
}
