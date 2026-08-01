<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Why this variant was served instead of the deterministic hash bucket.
 *
 * This is the *reason* axis. Where the value came from is the separate
 * {@see AssignmentSource} axis: a sticky-served forced variant is a legitimate
 * combination, so collapsing both into one enum would make it unrepresentable.
 *
 * Analytics counts only {@see self::Assigned} as experiment participation; every
 * other reason is excluded from reports.
 *
 * Consumers may read values outside this enum from storage: a delivery pipeline
 * that does not know the reason writes `fallback_unspecified`, which is
 * deliberately absent here because it can be read but never produced.
 *
 * @api
 */
enum DecisionReason: string
{
    /** Deterministic weighted bucket — the only reason included in analysis. */
    case Assigned = 'assigned';

    /** Explicit override, typically QA. Never enters analysis. */
    case Forced = 'forced';

    /** Experiment is disabled: the kill switch returned the fallback variant. */
    case FallbackDisabled = 'fallback_disabled';

    /** Targeting rules did not match the assignment context. */
    case FallbackTargetingMismatch = 'fallback_targeting_mismatch';

    /**
     * Whether the variant is a fallback rather than a real assignment.
     *
     * Both fallback reasons answer yes; they stay distinct because a disabled
     * experiment and a targeting miss are different operational situations that
     * v1 could not tell apart.
     */
    public function isFallback(): bool
    {
        return $this === self::FallbackDisabled || $this === self::FallbackTargetingMismatch;
    }

    /**
     * Whether events with this reason belong in experiment analysis.
     */
    public function isAnalyzable(): bool
    {
        return $this === self::Assigned;
    }
}
