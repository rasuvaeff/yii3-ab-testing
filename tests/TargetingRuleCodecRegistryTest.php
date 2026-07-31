<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AndTargetingRule;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Rasuvaeff\Yii3AbTesting\OrTargetingRule;
use Rasuvaeff\Yii3AbTesting\TargetingRule;
use Rasuvaeff\Yii3AbTesting\TargetingRuleCodec;
use Rasuvaeff\Yii3AbTesting\TargetingRuleCodecRegistry;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(TargetingRuleCodecRegistry::class)]
final class TargetingRuleCodecRegistryTest
{
    public function decodesNestedBuiltInRules(): void
    {
        $registry = new TargetingRuleCodecRegistry();

        $rule = $registry->decode([
            'type' => 'and',
            'rules' => [
                ['type' => 'environment', 'values' => ['production']],
                [
                    'type' => 'or',
                    'rules' => [
                        ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
                        ['type' => 'attribute', 'attribute' => 'beta', 'value' => true],
                    ],
                ],
            ],
        ]);

        Assert::instanceOf($rule, AndTargetingRule::class);
        Assert::true($rule->matches(
            AssignmentContext::forEnvironment('production')->withAttribute('plan', 'pro'),
        ));
        Assert::false($rule->matches(AssignmentContext::forEnvironment('staging')));
    }

    public function builtInRuleClassesAreSelected(): void
    {
        $registry = new TargetingRuleCodecRegistry();

        Assert::instanceOf(
            $registry->decode(['type' => 'environment', 'values' => ['production']]),
            EnvironmentTargetingRule::class,
        );
        Assert::instanceOf(
            $registry->decode(['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro']),
            AttributeTargetingRule::class,
        );
        Assert::instanceOf(
            $registry->decode([
                'type' => 'or',
                'rules' => [['type' => 'environment', 'values' => ['production']]],
            ]),
            OrTargetingRule::class,
        );
    }

    public function customCodecExtendsRegistry(): void
    {
        $customRule = new readonly class implements TargetingRule {
            #[\Override]
            public function matches(AssignmentContext $context): bool
            {
                return $context->getEnvironment() === 'production';
            }
        };
        $codec = new readonly class ($customRule) implements TargetingRuleCodec {
            public function __construct(private TargetingRule $rule) {}

            #[\Override]
            public function supports(string $type): bool
            {
                return $type === 'always-production';
            }

            #[\Override]
            public function supportsRule(TargetingRule $rule): bool
            {
                return $rule === $this->rule;
            }

            /**
             * @param array<string, mixed> $data
             * @param \Closure(mixed): TargetingRule $decode
             */
            #[\Override]
            public function decode(array $data, \Closure $decode): TargetingRule
            {
                return $this->rule;
            }

            /**
             * @param \Closure(TargetingRule): array<string, mixed> $encode
             * @return array<string, mixed>
             */
            #[\Override]
            public function encode(TargetingRule $rule, \Closure $encode): array
            {
                return ['type' => 'always-production'];
            }
        };
        $registry = new TargetingRuleCodecRegistry($codec);

        $rule = $registry->decode(['type' => 'always-production']);

        Assert::true($rule->matches(AssignmentContext::forEnvironment('production')));
        Assert::same($registry->encode($rule), ['type' => 'always-production']);
    }

    public function encodesAndDecodesNestedBuiltInRules(): void
    {
        $registry = new TargetingRuleCodecRegistry();
        $rule = new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
            new OrTargetingRule(rules: [
                new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
                new AttributeTargetingRule(attribute: 'beta', value: true),
            ]),
        ]);

        $encoded = $registry->encode($rule);

        Assert::same($encoded, [
            'type' => 'and',
            'rules' => [
                ['type' => 'environment', 'values' => ['production']],
                [
                    'type' => 'or',
                    'rules' => [
                        ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
                        ['type' => 'attribute', 'attribute' => 'beta', 'value' => true],
                    ],
                ],
            ],
        ]);
        Assert::instanceOf($registry->decode($encoded), AndTargetingRule::class);
    }

    public function rejectsUnknownType(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Unknown targeting rule type: "missing"');

        (new TargetingRuleCodecRegistry())->decode(['type' => 'missing']);
    }

    public function rejectsListInsteadOfObject(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Invalid targeting rule: expected object, got array');

        (new TargetingRuleCodecRegistry())->decode([]);
    }

    public function rejectsScalarInsteadOfObject(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Invalid targeting rule: expected object, got string');

        (new TargetingRuleCodecRegistry())->decode('environment');
    }

    public function rejectsPopulatedListInsteadOfObject(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Invalid targeting rule: expected object, got array');

        (new TargetingRuleCodecRegistry())->decode([['type' => 'environment']]);
    }

    public function rejectsNonStringType(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Invalid targeting rule: "type" must be a non-empty string');

        (new TargetingRuleCodecRegistry())->decode(['type' => 42]);
    }

    public function rejectsEmptyType(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Invalid targeting rule: "type" must be a non-empty string');

        (new TargetingRuleCodecRegistry())->decode(['type' => '']);
    }
}
