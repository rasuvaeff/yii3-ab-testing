<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ConfigExperimentProvider::class)]
final class ConfigExperimentProviderTest
{
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

        Assert::array($experiments)->hasKeys('checkout-button');
        Assert::instanceOf($experiments['checkout-button'], Experiment::class);
    }

    public function returnsEmptyArrayForEmptyConfig(): void
    {
        $provider = new ConfigExperimentProvider(config: []);

        Assert::same($provider->getExperiments(), []);
    }

    public function returnsAllConfiguredExperiments(): void
    {
        $provider = new ConfigExperimentProvider([
            'first' => ['fallbackVariant' => 'a', 'variants' => ['a' => 100]],
            'second' => ['fallbackVariant' => 'b', 'variants' => ['b' => 100]],
        ]);

        $experiments = $provider->getExperiments();

        Assert::count($experiments, 2);
        Assert::array($experiments)->hasKeys('first');
        Assert::array($experiments)->hasKeys('second');
    }

    public function keysExperimentsByName(): void
    {
        $provider = new ConfigExperimentProvider([
            'my-test' => [
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        Assert::same($provider->getExperiments()['my-test']->name, 'my-test');
    }

    public function usesNameAsDefaultSalt(): void
    {
        $provider = new ConfigExperimentProvider([
            'my-test' => [
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        Assert::same($provider->getExperiments()['my-test']->salt, 'my-test');
    }

    public function usesExplicitSaltWhenProvided(): void
    {
        $provider = new ConfigExperimentProvider([
            'my-exp' => [
                'salt' => 'custom-salt',
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        Assert::same($provider->getExperiments()['my-exp']->salt, 'custom-salt');
    }

    public function defaultsToEnabled(): void
    {
        $provider = new ConfigExperimentProvider([
            'test' => [
                'salt' => 's',
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
            ],
        ]);

        Assert::true($provider->getExperiments()['test']->enabled);
    }

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

        Assert::false($provider->getExperiments()['test']->enabled);
    }
}
