<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidExperimentException;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidVariantException;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(Experiment::class)]
#[Covers(InvalidExperimentException::class)]
#[Covers(InvalidVariantException::class)]
final class ExperimentTest
{
    public function createsValidExperiment(): void
    {
        $exp = new Experiment(
            name: 'checkout-button',
            enabled: true,
            salt: 'checkout-v1',
            fallbackVariant: 'control',
            variants: ['control' => 50, 'green' => 50],
        );

        Assert::same($exp->name, 'checkout-button');
        Assert::true($exp->enabled);
        Assert::same($exp->salt, 'checkout-v1');
        Assert::same($exp->fallbackVariant, 'control');
        Assert::same($exp->variants, ['control' => 50, 'green' => 50]);
    }

    public function throwsOnInvalidExperimentName(): void
    {
        Expect::exception(InvalidExperimentException::class);

        new Experiment(
            name: 'INVALID',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );
    }

    public function throwsOnExperimentNameWithTrailingNewline(): void
    {
        Expect::exception(InvalidExperimentException::class);

        new Experiment(
            name: "checkout\n",
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );
    }

    public function throwsOnInvalidVariantName(): void
    {
        Expect::exception(InvalidVariantException::class);

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'A',
            variants: ['A' => 100],
        );
    }

    public function throwsOnEmptySalt(): void
    {
        try {
            new Experiment(
                name: 'test',
                enabled: true,
                salt: '',
                fallbackVariant: 'a',
                variants: ['a' => 100],
            );
            Assert::fail('Expected InvalidExperimentException');
        } catch (InvalidExperimentException $e) {
            Assert::string($e->getMessage())->contains('Salt must not be empty');
        }
    }

    public function emptySaltMessageContainsExperimentName(): void
    {
        try {
            new Experiment(
                name: 'my-exp',
                enabled: true,
                salt: '',
                fallbackVariant: 'a',
                variants: ['a' => 100],
            );
            Assert::fail('Expected InvalidExperimentException');
        } catch (InvalidExperimentException $e) {
            Assert::string($e->getMessage())->contains('my-exp');
        }
    }

    public function throwsOnEmptyVariants(): void
    {
        try {
            new Experiment(
                name: 'test',
                enabled: true,
                salt: 'salt',
                fallbackVariant: 'a',
                variants: [],
            );
            Assert::fail('Expected InvalidExperimentException');
        } catch (InvalidExperimentException $e) {
            Assert::string($e->getMessage())->contains('must have at least one variant');
        }
    }

    public function throwsOnMissingFallbackVariant(): void
    {
        Expect::exception(InvalidExperimentException::class);

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'missing',
            variants: ['a' => 50, 'b' => 50],
        );
    }

    public function throwsOnZeroTotalWeight(): void
    {
        Expect::exception(InvalidExperimentException::class);

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 0, 'b' => 0],
        );
    }

    public function targetingIsNullByDefault(): void
    {
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );

        Assert::null($exp->targeting);
    }

    public function acceptsTargetingRule(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'plan', value: 'pro');
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
            targeting: $rule,
        );

        Assert::same($exp->targeting, $rule);
    }

    public function acceptsConfigurationId(): void
    {
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
            configurationId: 'revision-42',
        );

        Assert::same($exp->configurationId, 'revision-42');
    }

    public function rejectsEmptyConfigurationId(): void
    {
        Expect::exception(InvalidExperimentException::class)
            ->withMessage('Configuration ID must not be empty in experiment "test"');

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
            configurationId: '',
        );
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function invalidWeightProvider(): iterable
    {
        yield 'negative' => [-10, 'must not be negative'];
        yield 'minus one' => [-1, 'must not be negative'];
        yield 'float' => [0.5, 'must be an integer, got float'];
        yield 'whole float' => [50.0, 'must be an integer, got float'];
        yield 'numeric string' => ['50', 'must be an integer, got string'];
        yield 'null' => [null, 'must be an integer, got null'];
        yield 'bool' => [true, 'must be an integer, got bool'];
        yield 'array' => [[50], 'must be an integer, got array'];
    }

    /**
     * A negative weight used to pass every check: `array_sum()` of
     * `['control' => -10, 'test' => 30]` is 20, which clears `> 0`. The
     * cumulative boundary in `WeightedHashAssignmentStrategy` then went
     * backwards, `control` became unreachable, and the experiment ran a
     * distribution nobody had configured — with no error anywhere.
     */
    #[DataProvider('invalidWeightProvider')]
    public function rejectsInvalidVariantWeight(mixed $weight, string $needle): void
    {
        Expect::exception(InvalidExperimentException::class)->withMessageContaining($needle);

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 30, 'b' => $weight],
        );
    }

    /**
     * Zero is a legitimate weight — it keeps a variant defined while routing no
     * traffic to it — so the rejection must be `< 0`, never `<= 0`.
     */
    public function acceptsZeroWeight(): void
    {
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100, 'b' => 0],
        );

        Assert::same($exp->variants, ['a' => 100, 'b' => 0]);
    }

    public function invalidWeightMessageNamesTheVariantAndExperiment(): void
    {
        Expect::exception(InvalidExperimentException::class)
            ->withMessage('Weight of variant "green" in experiment "checkout" must not be negative, got -7');

        new Experiment(
            name: 'checkout',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'control',
            variants: ['control' => 50, 'green' => -7],
        );
    }

    /**
     * Known edge cases, pinned deterministically ahead of the random phase:
     * the two ways a weight can be invalid, the boundary that must stay valid,
     * and the negative-plus-positive pair whose sum still clears `> 0`.
     *
     * @return iterable<string, array{0: mixed}>
     */
    public static function weightIsAcceptedOnlyWhenNonNegativeIntExamples(): iterable
    {
        yield 'zero boundary' => [0];
        yield 'minus one boundary' => [-1];
        yield 'sum still positive' => [-10];
        yield 'float' => [1.5];
        yield 'numeric string' => ['30'];
        yield 'large' => [PHP_INT_MAX];
    }

    /**
     * Whatever the weight, construction either succeeds with the weight stored
     * verbatim or throws `InvalidExperimentException` — it never accepts a
     * value it cannot represent, and never rewrites one it accepted.
     */
    #[Property(runs: 300)]
    public function weightIsAcceptedOnlyWhenNonNegativeInt(mixed $weight): void
    {
        $accepted = \is_int($weight) && $weight >= 0;

        Classify::cover($accepted, 'accepted', 20.0);
        Classify::cover(!$accepted, 'rejected', 20.0);
        Classify::when(\is_int($weight) && $weight < 0, 'negative int');
        Classify::when(\is_float($weight), 'float');

        try {
            $exp = new Experiment(
                name: 'test',
                enabled: true,
                salt: 'salt',
                fallbackVariant: 'a',
                variants: ['a' => 30, 'b' => $weight],
            );
        } catch (InvalidExperimentException) {
            Assert::false($accepted);

            return;
        }

        Assert::true($accepted);
        Assert::same($exp->variants, ['a' => 30, 'b' => $weight]);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function weightIsAcceptedOnlyWhenNonNegativeIntGenerators(): array
    {
        // `Gen::frequency`, not `Gen::oneOf`: the latter picks among the given
        // *values*, so handing it generators yields the generator objects
        // themselves — every run would be rejected and the coverage gate below
        // would be the only thing that noticed.
        return [
            'weight' => Gen::frequency([
                [4, Gen::intBetween(0, 100)],
                [2, Gen::intBetween(-100, -1)],
                [2, Gen::floatBetween(-100.0, 100.0)],
                [1, Gen::stringAscii()],
                [1, Gen::bool()],
            ]),
        ];
    }

    /**
     * The distribution guarantee the validation exists to protect: for any
     * accepted variant map, no cumulative boundary can go backwards, so every
     * variant with a non-zero weight stays reachable.
     */
    #[Property(runs: 300)]
    public function acceptedWeightsNeverProduceADecreasingCumulativeBoundary(int $w1, int $w2, int $w3): void
    {
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => $w1, 'b' => $w2, 'c' => $w3],
        );

        $cumulative = 0;

        foreach ($exp->variants as $weight) {
            $next = $cumulative + $weight;
            Assert::true($next >= $cumulative);
            $cumulative = $next;
        }

        Assert::same($cumulative, $w1 + $w2 + $w3);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function acceptedWeightsNeverProduceADecreasingCumulativeBoundaryGenerators(): array
    {
        return [
            'w1' => Gen::intBetween(1, 1000),
            'w2' => Gen::intBetween(0, 1000),
            'w3' => Gen::intBetween(0, 1000),
        ];
    }

    public static function validNameProvider(): iterable
    {
        yield 'simple' => ['checkout'];
        yield 'hyphenated' => ['checkout-button'];
        yield 'underscore' => ['checkout_button'];
        yield 'with digits' => ['test2'];
    }

    #[DataProvider('validNameProvider')]
    public function acceptsValidNames(string $name): void
    {
        $exp = new Experiment(
            name: $name,
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );

        Assert::same($exp->name, $name);
    }
}
