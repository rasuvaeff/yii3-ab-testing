<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AndTargetingRule;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Rasuvaeff\Yii3AbTesting\TargetingRule;
use Rasuvaeff\Yii3AbTesting\TargetingRuleCodec;
use Rasuvaeff\Yii3AbTesting\TargetingRuleCodecRegistry;
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

    public function decodesNestedTargetingRules(): void
    {
        $provider = new ConfigExperimentProvider([
            'targeted' => [
                'fallbackVariant' => 'control',
                'variants' => ['control' => 50, 'green' => 50],
                'targeting' => [
                    'type' => 'and',
                    'rules' => [
                        ['type' => 'environment', 'values' => ['production']],
                        ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
                    ],
                ],
            ],
        ]);

        $targeting = $provider->getExperiments()['targeted']->targeting;

        Assert::instanceOf($targeting, AndTargetingRule::class);
        Assert::true($targeting->matches(
            AssignmentContext::forEnvironment('production')->withAttribute('plan', 'pro'),
        ));
    }

    public function derivesStableConfigurationIdIndependentOfMapOrder(): void
    {
        $first = new ConfigExperimentProvider([
            'test' => [
                'salt' => 'salt',
                'fallbackVariant' => 'a',
                'variants' => ['b' => 40, 'a' => 60],
            ],
        ]);
        $second = new ConfigExperimentProvider([
            'test' => [
                'variants' => ['a' => 60, 'b' => 40],
                'fallbackVariant' => 'a',
                'salt' => 'salt',
            ],
        ]);

        Assert::same(
            $first->getExperiments()['test']->configurationId,
            $second->getExperiments()['test']->configurationId,
        );
    }

    public function configurationIdChangesWithDefinition(): void
    {
        $first = new ConfigExperimentProvider([
            'test' => ['fallbackVariant' => 'a', 'variants' => ['a' => 60, 'b' => 40]],
        ]);
        $second = new ConfigExperimentProvider([
            'test' => ['fallbackVariant' => 'a', 'variants' => ['a' => 50, 'b' => 50]],
        ]);

        Assert::notSame(
            $first->getExperiments()['test']->configurationId,
            $second->getExperiments()['test']->configurationId,
        );
    }

    public function acceptsExplicitConfigurationId(): void
    {
        $provider = new ConfigExperimentProvider([
            'test' => [
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
                'configurationId' => 'revision-42',
            ],
        ]);

        Assert::same($provider->getExperiments()['test']->configurationId, 'revision-42');
    }

    public function configurationIdSeparatesFloatAndIntegerTargetingValues(): void
    {
        $definition = static fn(float|int $value): array => [
            'test' => [
                'fallbackVariant' => 'a',
                'variants' => ['a' => 100],
                'targeting' => ['type' => 'attribute', 'attribute' => 'score', 'value' => $value],
            ],
        ];

        Assert::notSame(
            (new ConfigExperimentProvider($definition(1.0)))->getExperiments()['test']->configurationId,
            (new ConfigExperimentProvider($definition(1)))->getExperiments()['test']->configurationId,
        );
    }

    public function usesTheInjectedCodecRegistry(): void
    {
        $codec = new class implements TargetingRuleCodec {
            #[\Override]
            public function supports(string $type): bool
            {
                return $type === 'always';
            }

            #[\Override]
            public function supportsRule(TargetingRule $rule): bool
            {
                return false;
            }

            #[\Override]
            public function decode(array $data, \Closure $decode): TargetingRule
            {
                return new class implements TargetingRule {
                    #[\Override]
                    public function matches(AssignmentContext $context): bool
                    {
                        return true;
                    }
                };
            }

            #[\Override]
            public function encode(TargetingRule $rule, \Closure $encode): array
            {
                throw new \LogicException('Not encodable');
            }
        };

        $provider = new ConfigExperimentProvider(
            config: [
                'test' => [
                    'fallbackVariant' => 'a',
                    'variants' => ['a' => 100],
                    'targeting' => ['type' => 'always'],
                ],
            ],
            targetingCodecs: new TargetingRuleCodecRegistry($codec),
        );

        Assert::true(
            $provider->getExperiments()['test']->targeting?->matches(new AssignmentContext()) ?? false,
        );
    }
}
