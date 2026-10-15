---
title: "DI: one source per key"
description: Why the core binds only the facade and the strategy, which package owns ExperimentProvider and the trackers, and how yiisoft/config's Duplicate key error protects the setup.
---

# DI: one source per key

`yiisoft/config` refuses to merge two vendor packages that define the same key
in the same group — it throws `Duplicate key "<class>"`. The family is built
around that rule instead of fighting it: every key has exactly one owner.

## What the core binds

| Key | Bound by the core | Why |
|---|---|---|
| `AbTesting` | yes | the facade |
| `AssignmentStrategy` | yes | `WeightedHashAssignmentStrategy`, the only algorithm |
| `ExperimentProvider` | **no** | the experiment source — owned by the application or a storage package |
| `ExposureTracker` | **no** | an event sink — owned by the application or one adapter |
| `ConversionTracker` | **no** | an event sink — same |

`AbTesting` requires an `ExperimentProvider` and has no default, so without a
binding it does not resolve. That is intentional: two sources of experiment
truth is the bug, and a missing one should fail loudly.

The trackers do have defaults — the no-op `NullExposureTracker` and
`NullConversionTracker` — so a fresh install runs and records nothing.

## Who owns what

| Setup | `ExperimentProvider` | Trackers |
|---|---|---|
| Core only | your app → `ConfigExperimentProvider` | your app → `Logger*` sinks, or nothing |
| `+ yii3-ab-testing-db` | the db package → `DbExperimentProvider` | unchanged |
| `+ yii3-ab-testing-outbox` | unchanged | the outbox package, or your app |
| Both sinks at once | — | your app → `CompositeExposureTracker` / `CompositeConversionTracker` |

`yii3-ab-testing-clickhouse` binds nothing since 2.0. It used to bind both
trackers, which made installing it next to the outbox adapter a `Duplicate key`
error.

## The two mistakes

**A storage package plus a manual binding.** Installing `yii3-ab-testing-db` *and*
binding `ConfigExperimentProvider` in `config/common/di/*.php` is the same
conflict as two vendor packages: delete the manual binding when the storage
package arrives.

**Two adapters for one sink.** If two installed packages both bind
`ExposureTracker`, pick one, or compose them once in your application — the
composite is the only place fan-out belongs.

## Overriding in the application

The application layer may override a vendor binding; that is how you replace
an adapter's default tracker with a composite. The override lives in your
`config/common/di/*.php`, and it is the only binding of that key you write.
