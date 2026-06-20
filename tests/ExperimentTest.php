<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidExperimentException;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidVariantException;
use Rasuvaeff\Yii3AbTesting\Experiment;

#[CoversClass(Experiment::class)]
final class ExperimentTest extends TestCase
{
    #[Test]
    public function createsValidExperiment(): void
    {
        $exp = new Experiment(
            name: 'checkout-button',
            enabled: true,
            salt: 'checkout-v1',
            fallbackVariant: 'control',
            variants: ['control' => 50, 'green' => 50],
        );

        $this->assertSame('checkout-button', $exp->name);
        $this->assertTrue($exp->enabled);
        $this->assertSame('checkout-v1', $exp->salt);
        $this->assertSame('control', $exp->fallbackVariant);
        $this->assertSame(['control' => 50, 'green' => 50], $exp->variants);
    }

    #[Test]
    public function throwsOnInvalidExperimentName(): void
    {
        $this->expectException(InvalidExperimentException::class);

        new Experiment(
            name: 'INVALID',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );
    }

    #[Test]
    public function throwsOnInvalidVariantName(): void
    {
        $this->expectException(InvalidVariantException::class);

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'A',
            variants: ['A' => 100],
        );
    }

    #[Test]
    public function throwsOnEmptySalt(): void
    {
        $this->expectException(InvalidExperimentException::class);
        $this->expectExceptionMessage('Salt must not be empty');

        new Experiment(
            name: 'test',
            enabled: true,
            salt: '',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );
    }

    #[Test]
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
        } catch (InvalidExperimentException $e) {
            $this->assertStringContainsString('my-exp', $e->getMessage());

            return;
        }

        $this->fail('Exception was not thrown');
    }

    #[Test]
    public function throwsOnEmptyVariants(): void
    {
        $this->expectException(InvalidExperimentException::class);
        $this->expectExceptionMessage('must have at least one variant');

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: [],
        );
    }

    #[Test]
    public function throwsOnMissingFallbackVariant(): void
    {
        $this->expectException(InvalidExperimentException::class);

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'missing',
            variants: ['a' => 50, 'b' => 50],
        );
    }

    #[Test]
    public function throwsOnZeroTotalWeight(): void
    {
        $this->expectException(InvalidExperimentException::class);

        new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 0, 'b' => 0],
        );
    }

    #[Test]
    public function targetingIsNullByDefault(): void
    {
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );

        $this->assertNull($exp->targeting);
    }

    #[Test]
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

        $this->assertSame($rule, $exp->targeting);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNameProvider(): iterable
    {
        yield 'simple' => ['checkout'];
        yield 'hyphenated' => ['checkout-button'];
        yield 'underscore' => ['checkout_button'];
        yield 'with digits' => ['test2'];
    }

    #[DataProvider('validNameProvider')]
    #[Test]
    public function acceptsValidNames(string $name): void
    {
        $exp = new Experiment(
            name: $name,
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );

        $this->assertSame($name, $exp->name);
    }
}
