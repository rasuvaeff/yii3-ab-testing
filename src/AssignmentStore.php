<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Persists an assigned variant per (experiment, subject) so a subject keeps the
 * same variant even when experiment weights or the variant set change later.
 *
 * Deterministic assignment alone keeps a subject in the same variant only while
 * weights are stable; changing them shifts bucket boundaries. A store survives
 * that. Sticky resolution is a separate layer — the {@see AbTesting} facade
 * stays pure. Implementations live in the web/persistence adapter packages.
 *
 * @api
 */
interface AssignmentStore
{
    /**
     * Returns the stored variant for the subject in the experiment, or null when
     * none has been stored yet.
     */
    public function get(string $experiment, string $subjectId): ?string;

    public function put(string $experiment, string $subjectId, string $variant): void;
}
