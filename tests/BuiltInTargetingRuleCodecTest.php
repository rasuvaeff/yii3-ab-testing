<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AndTargetingRule;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\BuiltInTargetingRuleCodec;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Rasuvaeff\Yii3AbTesting\OrTargetingRule;
use Rasuvaeff\Yii3AbTesting\TargetingRule;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(BuiltInTargetingRuleCodec::class)]
final class BuiltInTargetingRuleCodecTest
{
    public function supportsEveryBuiltInType(): void
    {
        $codec = new BuiltInTargetingRuleCodec();

        Assert::true($codec->supports('environment'));
        Assert::true($codec->supports('attribute'));
        Assert::true($codec->supports('and'));
        Assert::true($codec->supports('or'));
        Assert::false($codec->supports('custom'));
    }

    public function decodesEnvironmentRule(): void
    {
        $rule = $this->decode(['type' => 'environment', 'values' => ['production']]);

        Assert::instanceOf($rule, EnvironmentTargetingRule::class);
    }

    public function decodesAttributeRule(): void
    {
        $rule = $this->decode(['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro']);

        Assert::instanceOf($rule, AttributeTargetingRule::class);
    }

    public function decodesAndRule(): void
    {
        $rule = $this->decode([
            'type' => 'and',
            'rules' => [['type' => 'environment', 'values' => ['production']]],
        ]);

        Assert::instanceOf($rule, AndTargetingRule::class);
    }

    public function decodesOrRule(): void
    {
        $rule = $this->decode([
            'type' => 'or',
            'rules' => [['type' => 'environment', 'values' => ['production']]],
        ]);

        Assert::instanceOf($rule, OrTargetingRule::class);
    }

    public function rejectsAnUnsupportedType(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Unsupported built-in targeting rule type "custom"');

        $this->decode(['type' => 'custom']);
    }

    public function rejectsANonStringType(): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Invalid targeting rule: "type" must be a string');

        $this->decode(['type' => 42]);
    }

    #[DataProvider('invalidEnvironmentValuesProvider')]
    public function rejectsInvalidEnvironmentValues(mixed $values): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Invalid "environment" targeting rule: "values" must be a non-empty list of strings');

        $this->decode(['type' => 'environment', 'values' => $values]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidEnvironmentValuesProvider(): iterable
    {
        yield 'not an array' => ['production'];
        yield 'associative array' => [['first' => 'production']];
        yield 'empty list' => [[]];
        yield 'missing key' => [null];
        yield 'non-string element' => [['production', 42]];
    }

    #[DataProvider('attributeValueProvider')]
    public function decodesEveryScalarAttributeValue(mixed $value): void
    {
        $rule = $this->decode(['type' => 'attribute', 'attribute' => 'plan', 'value' => $value]);

        Assert::instanceOf($rule, AttributeTargetingRule::class);
        Assert::same($rule->getValue(), $value);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function attributeValueProvider(): iterable
    {
        yield 'string' => ['pro'];
        yield 'int' => [42];
        yield 'float' => [1.5];
        yield 'bool' => [true];
    }

    #[DataProvider('invalidAttributeRuleProvider')]
    public function rejectsInvalidAttributeRules(mixed $attribute, mixed $value, string $message): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessage($message);

        $this->decode(['type' => 'attribute', 'attribute' => $attribute, 'value' => $value]);
    }

    /**
     * @return iterable<string, array{mixed, mixed, string}>
     */
    public static function invalidAttributeRuleProvider(): iterable
    {
        yield 'non-string attribute' => [
            42,
            'pro',
            'Invalid "attribute" targeting rule: "attribute" must be a string',
        ];
        yield 'array value' => ['plan', ['pro'], 'Invalid targeting attribute value type'];
        yield 'null value' => ['plan', null, 'Invalid targeting attribute value type'];
    }

    #[DataProvider('invalidCompositeRulesProvider')]
    public function rejectsInvalidCompositeRules(string $type, mixed $rules): void
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage(sprintf('Invalid "%s" targeting rule: "rules" must be a non-empty list', $type));

        $this->decode(['type' => $type, 'rules' => $rules]);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidCompositeRulesProvider(): iterable
    {
        yield 'and: not an array' => ['and', 'nope'];
        yield 'and: associative array' => ['and', ['first' => ['type' => 'environment', 'values' => ['p']]]];
        yield 'and: empty list' => ['and', []];
        yield 'and: missing key' => ['and', null];
        yield 'or: not an array' => ['or', 'nope'];
        yield 'or: associative array' => ['or', ['first' => ['type' => 'environment', 'values' => ['p']]]];
        yield 'or: empty list' => ['or', []];
    }

    public function nestedCompositeRulesGoThroughTheInjectedDecoder(): void
    {
        $rule = $this->decode([
            'type' => 'and',
            'rules' => [
                ['type' => 'environment', 'values' => ['production']],
                ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
            ],
        ]);

        Assert::instanceOf($rule, AndTargetingRule::class);
        $nested = $rule->getRules();
        Assert::same(\count($nested), 2);
        Assert::instanceOf($nested[0], EnvironmentTargetingRule::class);
        Assert::instanceOf($nested[1], AttributeTargetingRule::class);
    }

    public function encodesEnvironmentRule(): void
    {
        Assert::same(
            $this->encode(new EnvironmentTargetingRule(environments: ['production', 'staging'])),
            ['type' => 'environment', 'values' => ['production', 'staging']],
        );
    }

    public function encodesAttributeRule(): void
    {
        Assert::same(
            $this->encode(new AttributeTargetingRule(attribute: 'plan', value: 'pro')),
            ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
        );
    }

    public function encodesAndRuleThroughTheInjectedEncoder(): void
    {
        Assert::same(
            $this->encode(new AndTargetingRule(rules: [
                new EnvironmentTargetingRule(environments: ['production']),
            ])),
            ['type' => 'and', 'rules' => [['nested' => EnvironmentTargetingRule::class]]],
        );
    }

    public function encodesOrRuleThroughTheInjectedEncoder(): void
    {
        Assert::same(
            $this->encode(new OrTargetingRule(rules: [
                new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
            ])),
            ['type' => 'or', 'rules' => [['nested' => AttributeTargetingRule::class]]],
        );
    }

    public function supportsEveryBuiltInRuleClass(): void
    {
        $codec = new BuiltInTargetingRuleCodec();

        Assert::true($codec->supportsRule(new EnvironmentTargetingRule(environments: ['production'])));
        Assert::true($codec->supportsRule(new AttributeTargetingRule(attribute: 'plan', value: 'pro')));
        Assert::true($codec->supportsRule(new AndTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
        ])));
        Assert::true($codec->supportsRule(new OrTargetingRule(rules: [
            new EnvironmentTargetingRule(environments: ['production']),
        ])));
        Assert::false($codec->supportsRule($this->foreignRule()));
    }

    public function rejectsAnUnsupportedRuleClassOnEncode(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        (new BuiltInTargetingRuleCodec())->encode(
            $this->foreignRule(),
            static fn(TargetingRule $nested): array => [],
        );
    }

    private function foreignRule(): TargetingRule
    {
        return new class implements TargetingRule {
            #[\Override]
            public function matches(AssignmentContext $context): bool
            {
                return true;
            }
        };
    }

    /** @return array<string, mixed> */
    private function encode(TargetingRule $rule): array
    {
        return (new BuiltInTargetingRuleCodec())->encode(
            $rule,
            static fn(TargetingRule $nested): array => ['nested' => $nested::class],
        );
    }

    /** @param array<string, mixed> $data */
    private function decode(array $data): TargetingRule
    {
        return (new BuiltInTargetingRuleCodec())->decode(
            data: $data,
            decode: fn(mixed $nested): TargetingRule => \is_array($nested)
                ? $this->decode($nested)
                : throw new \LogicException('Nested rule must be an object'),
        );
    }
}
