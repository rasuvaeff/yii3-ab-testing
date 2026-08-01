<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Where the served variant came from — the provenance axis, orthogonal to
 * {@see DecisionReason}.
 *
 * Which storage backs a sticky assignment (cookie, database, custom) is
 * deliberately not modelled: that is an operational detail, not a property of
 * the decision, and encoding it would make analytics depend on deployment.
 *
 * @api
 */
enum AssignmentSource: string
{
    /** Resolved during this request from the experiment definition. */
    case Computed = 'computed';

    /** Replayed from an {@see AssignmentStore} (what v1 called `isSticky`). */
    case Store = 'store';
}
