<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use InvalidArgumentException;

/**
 * @api
 */
final readonly class TargetingRuleCodecRegistry
{
    /** @var non-empty-list<TargetingRuleCodec> */
    private array $codecs;

    public function __construct(TargetingRuleCodec ...$codecs)
    {
        $registered = $codecs;
        $registered[] = new BuiltInTargetingRuleCodec();
        /** @var non-empty-list<TargetingRuleCodec> $registered */
        $this->codecs = $registered;
    }

    public function decode(mixed $data): TargetingRule
    {
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException(
                sprintf('Invalid targeting rule: expected object, got %s', get_debug_type($data)),
            );
        }

        /** @var array<string, mixed> $data */

        $type = $data['type'] ?? null;

        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('Invalid targeting rule: "type" must be a non-empty string');
        }

        foreach ($this->codecs as $codec) {
            if ($codec->supports($type)) {
                return $codec->decode($data, $this->decode(...));
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown targeting rule type: "%s"', $type));
    }

    /** @return array<string, mixed> */
    public function encode(TargetingRule $rule): array
    {
        foreach ($this->codecs as $codec) {
            if ($codec->supportsRule($rule)) {
                return $codec->encode($rule, $this->encode(...));
            }
        }

        throw new InvalidArgumentException(
            sprintf('No targeting rule codec registered for "%s"', $rule::class),
        );
    }
}
