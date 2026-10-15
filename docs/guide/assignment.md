---
title: Hash bucketing v1
description: How a subject is assigned a variant — sha256 of salt and subject id, a 32-bit bucket, cumulative weights — and why the algorithm is a versioned public contract.
---

# Hash bucketing v1

Assignment stores nothing. A subject's variant is a pure function of the
experiment's salt, its weights and the subject id, so every server — and every
analysis job that recomputes it — reaches the same answer.

## The algorithm

```
digest = sha256(salt + ':' + subjectId)   // 64 hex characters
hash   = hexdec(digest[0:8])              // first 8 hex digits: 32-bit unsigned
bucket = hash % totalWeight
```

Variants are sorted by name, their weights accumulated, and the subject gets
the first variant whose cumulative boundary exceeds `bucket`.

`WeightedHashAssignmentStrategy` implements it behind the `AssignmentStrategy`
interface; the core binds it in DI as the only strategy.

## It is a contract

This is algorithm **v1**. The hash input, the digest slice, the sorting and the
boundary rule do not change in a patch or minor release; an incompatible
algorithm would get an explicit version and a major release.

The reason is not tidiness. Analytics joins, offline re-computation and any
other system that buckets the same subjects depend on getting the same number
for the same input — the function is kept identical to the one in
`yii3-feature-flags` so the two can be bridged later.

## What moves a subject

| Change | Effect |
|---|---|
| Nothing | Same salt + same subject id → same variant, forever |
| `salt` | Full re-assignment — everyone is re-drawn. Use it to reset an experiment on purpose |
| Weights, or adding/removing a variant | Bucket boundaries move: **some** subjects change variant |
| `enabled: false` | Everyone gets `fallbackVariant`; re-enabling restores the original buckets |

The middle row is the one that surprises people. A 50/50 split moved to 10/90
re-assigns every subject whose bucket fell between the old and the new boundary.
If an experiment may be reweighted while it runs, persist assignments — see
[Sticky assignments](/guide/persistence). To freeze a cohort and start a new
one, create a new experiment with a new salt.

## Weights

Every weight is a non-negative integer, checked by the `Experiment` constructor:
a fractional, numeric-string or negative weight throws
`InvalidExperimentException`. Negative weights are the reason the check exists —
the total can still be positive while a boundary runs backwards, and the variant
before it silently becomes unreachable.

Zero is valid and useful: it keeps a variant defined (so forced QA traffic and
old sticky pins still resolve) while routing no new traffic to it.

The total is checked again after summing: integers that each fit can overflow
`PHP_INT_MAX` together, `array_sum()` then returns a float, and the modulo above
breaks. Both `Experiment` and a direct `WeightedHashAssignmentStrategy` call
reject such a map up front.

## Forcing a variant

```php
$assignment = $ab->assign(
    experiment: 'checkout-button',
    subjectId: $userId,
    forcedVariant: 'green',
);
```

A forced variant must exist in the experiment — an unknown one throws
`InvalidVariantException` instead of being trusted. It bypasses targeting, and
it is recorded with reason `forced`, which keeps QA traffic out of every report;
see [Decision reason and source](/guide/decision).
