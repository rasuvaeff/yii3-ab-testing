<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Selects which context attributes are safe to persist as event dimensions.
 *
 * Lives in the core rather than in a delivery adapter so that every path —
 * durable outbox, log shipping, or a custom sink — applies the same rule. A
 * policy owned by one adapter would mean the same application leaks different
 * attributes depending on how events happen to be delivered.
 *
 * @api
 */
interface AnalyticsContextPolicy
{
    /**
     * @return array<string, scalar> Attributes cleared for analytics storage;
     *     everything not explicitly allowed is dropped, not redacted.
     */
    public function apply(?AssignmentContext $context): array;
}
