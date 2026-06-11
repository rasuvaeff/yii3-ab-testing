<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class Assignment
{
    public function __construct(
        public string $experiment,
        public string $variant,
        public string $subjectId,
        public bool $isForced = false,
        public bool $isFallback = false,
        public ?AssignmentContext $context = null,
        public bool $isSticky = false,
    ) {}

    public function isVariant(string $variant): bool
    {
        return $this->variant === $variant;
    }
}
