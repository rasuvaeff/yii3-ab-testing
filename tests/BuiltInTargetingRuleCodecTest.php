<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\BuiltInTargetingRuleCodec;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Testo\Assert;
use Testo\Codecov\Covers;
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
        $codec = new BuiltInTargetingRuleCodec();

        $rule = $codec->decode(
            data: ['type' => 'environment', 'values' => ['production']],
            decode: static fn(mixed $data): never => throw new \LogicException((string) $data),
        );

        Assert::instanceOf($rule, EnvironmentTargetingRule::class);
    }
}
