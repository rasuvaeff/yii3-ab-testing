---
title: Decision reason and source
description: Every assignment answers two independent questions — why this variant (assigned, forced, fallback) and where the value came from (computed or a store). Only "assigned" counts as participation.
---

# Decision reason and source

An `Assignment` carries two enums. They answer different questions and vary
independently, so neither can be derived from the other.

| Question | Field | Values |
|---|---|---|
| Why this variant rather than the hash bucket? | `reason` (`DecisionReason`) | `assigned`, `forced`, `fallback_disabled`, `fallback_targeting_mismatch` |
| Where did the value come from? | `source` (`AssignmentSource`) | `computed`, `store` |

```php
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\DecisionReason;

$assignment->reason === DecisionReason::FallbackTargetingMismatch;
$assignment->source === AssignmentSource::Store;

$assignment->isForced();
$assignment->isFallback();           // either fallback reason
$assignment->isTargetingMismatch();
$assignment->isSticky();             // source === store
```

## Why two axes

With a single "fallback" flag, two very different situations look the same: an
experiment someone switched off, and a subject outside the target segment. The
first is an operational event; the second is normal traffic that was never
eligible. Reports and alerts need to tell them apart.

The source axis is separate because a stored value can carry any reason. A
forced variant served back from a sticky cookie is `forced` + `store` — a
legitimate combination that one combined field could not express.

## Only `assigned` is participation

| Reason | Counts in analysis | Typical cause |
|---|---|---|
| `assigned` | yes | The subject was bucketed by the hash |
| `forced` | no | QA or a support override |
| `fallback_disabled` | no | The kill switch is off |
| `fallback_targeting_mismatch` | no | The subject did not match the targeting rules |

`DecisionReason::isAnalyzable()` states the rule in code, and both
`ExposureEvent` and `ConversionEvent` expose it as `isAnalyzable()`. Every
query in [Reading results](/guide/analytics) filters on
`decision_reason = 'assigned'`.

Events are still recorded for the other reasons. Excluding them at write time
would make an accidental kill switch or a broken targeting rule invisible in
the very data you would use to notice it.

## On the wire

Both values travel in every event row as `decision_reason` and
`assignment_source` — see [Events v2](/guide/events).
