<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

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
