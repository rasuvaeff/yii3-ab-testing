<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\Experiment;

#[CoversClass(ConfigExperimentProvider::class)]
final class ConfigExperimentProviderTest extends TestCase
{
    #[Test]
    public function buildsExperimentsFromConfig(): void
    {
        $provider = new ConfigExperimentProvider([
            'checkout-button' => [
                'enabled' => true,
                'salt' => 'checkout-v1',
                'fallbackVariant' => 'control',
                'variants' => ['control' => 50, 'green' => 50],
            ],
        ]);

        $experiments = $provider->getExperiments();

        $this->assertArrayHasKey('checkout-button', $experiments);
        $this->assertInstanceOf(Experiment::class, $experiments['checkout-button']);
    }

    #[Test]
    public function returnsEmptyArrayForEmptyConfig(): void
    {
        $provider = new ConfigExperimentProvider(config: []);

        $this->assertSame([], $provider->getExperiments());
    }

    #[Test]
    public function returnsAllConfiguredExperiments(): void
    {
        $provider = new ConfigExperimentProvider([
            'first' => ['fallbackVariant' => 'a', 'variants' => ['a' => 100]],
            'second' => ['fallbackVariant' => 'b', 'variants' => ['b' => 100]],
        ]);

        $experiments = $provider->getExperiments();

        $this->assertCount(2, $experiments);
        $this->assertArrayHasKey('first', $experiments);
        $this->assertArrayHasKey('second', $experiments);
    }

    #[Test]
    public function keysExperimentsByName(): void
    {
        $provider = new ConfigExperimentProvider([
            'my-test' => [
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        $this->assertSame('my-test', $provider->getExperiments()['my-test']->name);
    }

    #[Test]
    public function usesNameAsDefaultSalt(): void
    {
        $provider = new ConfigExperimentProvider([
            'my-test' => [
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        $this->assertSame('my-test', $provider->getExperiments()['my-test']->salt);
    }

    #[Test]
    public function usesExplicitSaltWhenProvided(): void
    {
        $provider = new ConfigExperimentProvider([
            'my-exp' => [
                'salt' => 'custom-salt',
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        $this->assertSame('custom-salt', $provider->getExperiments()['my-exp']->salt);
    }

    #[Test]
    public function defaultsToEnabled(): void
    {
        $provider = new ConfigExperimentProvider([
            'test' => [
                'salt' => 's',
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        $this->assertTrue($provider->getExperiments()['test']->enabled);
    }

    #[Test]
    public function respectsExplicitDisabled(): void
    {
        $provider = new ConfigExperimentProvider([
            'test' => [
                'enabled' => false,
                'salt' => 's',
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        $this->assertFalse($provider->getExperiments()['test']->enabled);
    }
}
