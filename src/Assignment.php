<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * The outcome of resolving a subject into a variant.
 *
 * Two independent axes describe it: {@see DecisionReason} says *why* this
 * variant rather than the hash bucket, {@see AssignmentSource} says *where* the
 * value came from. They replace the four overlapping booleans of v1, which
 * could not express a sticky-served forced variant and, worse, could not tell a
 * disabled experiment apart from a targeting miss once serialized.
 *
 * @api
 */
final readonly class Assignment
{
    public function __construct(
        public string $experiment,
        public string $variant,
        public string $subjectId,
        public DecisionReason $reason = DecisionReason::Assigned,
        public AssignmentSource $source = AssignmentSource::Computed,
        public ?AssignmentContext $context = null,
        public ?string $configurationId = null,
    ) {}

    public function isVariant(string $variant): bool
    {
        return $this->variant === $variant;
    }

    /** Served the fallback variant, for either of the two reasons. */
    public function isFallback(): bool
    {
        return $this->reason->isFallback();
    }

    /** Overridden explicitly, bypassing targeting and any store. */
    public function isForced(): bool
    {
        return $this->reason === DecisionReason::Forced;
    }

    /** Fell back because the targeting rules did not match the context. */
    public function isTargetingMismatch(): bool
    {
        return $this->reason === DecisionReason::FallbackTargetingMismatch;
    }

    /** Replayed from an {@see AssignmentStore} rather than computed. */
    public function isSticky(): bool
    {
        return $this->source === AssignmentSource::Store;
    }
}
