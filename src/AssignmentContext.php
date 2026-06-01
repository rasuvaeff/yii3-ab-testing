<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class AssignmentContext
{
    /**
     * @param array<string, scalar> $attributes
     */
    public function __construct(
        private ?string $environment = null,
        private array $attributes = [],
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public static function forEnvironment(string $environment): self
    {
        return new self(environment: $environment);
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    /**
     * @return array<string, scalar>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name): string|int|float|bool|null
    {
        return $this->attributes[$name] ?? null;
    }

    public function withEnvironment(string $environment): self
    {
        return new self(environment: $environment, attributes: $this->attributes);
    }

    public function withAttribute(string $name, string|int|float|bool $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self(environment: $this->environment, attributes: $attributes);
    }
}
