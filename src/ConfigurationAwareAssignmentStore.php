<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Optional extension for stores that invalidate a sticky variant when the
 * experiment configuration changes.
 *
 * A plain {@see AssignmentStore} pins a subject to a variant forever. That is
 * right while the experiment is stable and wrong once it is reweighted: the
 * subject keeps a variant drawn from boundaries that no longer exist. A
 * configuration-aware store reuses the stored variant only for the
 * configuration it was drawn from.
 *
 * Lives in the core rather than in one adapter because more than one storage
 * implements it — a signed cookie in the web package, a table in the database
 * package — and they must not have to depend on each other to share the
 * contract.
 *
 * @api
 */
interface ConfigurationAwareAssignmentStore extends AssignmentStore
{
    /**
     * @param string|null $configurationId Identity of the experiment definition
     *     the caller is resolving against; null when it is unknown.
     *
     * @return string|null The stored variant, or null when nothing is stored
     *     for this configuration — including when a variant is stored for a
     *     *different* one.
     */
    public function getForConfiguration(
        string $experiment,
        string $subjectId,
        ?string $configurationId,
    ): ?string;

    public function putForConfiguration(
        string $experiment,
        string $subjectId,
        string $variant,
        ?string $configurationId,
    ): void;
}
