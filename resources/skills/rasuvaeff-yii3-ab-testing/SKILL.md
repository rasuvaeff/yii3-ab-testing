---
name: rasuvaeff-yii3-ab-testing
description: >-
  Deterministic A/B testing for Yii3 with rasuvaeff/yii3-ab-testing —
  AbTesting facade, Experiment, Assignment, WeightedHashAssignmentStrategy,
  ConfigExperimentProvider, targeting rules, exposure/conversion trackers.
  Use when writing, reviewing or debugging experiment assignment, variant
  bucketing, targeting or exposure/conversion tracking in a project that has
  this package installed.
---

# rasuvaeff/yii3-ab-testing

Stateless, deterministic A/B testing: a subject id is hashed into a weighted
variant bucket; exposures and conversions are tracked explicitly.
Namespace `Rasuvaeff\Yii3AbTesting\`.

## Safety rules — verify these on every change

1. **Bucketing is deterministic.** Variant = hash of `salt . ':' . subjectId`
   (sha256, first 8 hex, 32-bit bucket). Same subject always gets the same
   variant. Changing the salt re-randomizes the whole experiment; changing
   weights or adding/removing variants shifts bucket boundaries. Never "tweak"
   a salt on a running experiment. This is bucketing algorithm v1; patch and
   minor releases must not change its hash, sorting or boundary rules.

2. **`assign()` / `is()` are pure — no auto-tracking.** Exposures and
   conversions are recorded only via explicit `trackExposure()` /
   `trackConversion()`, and events flow through configured tracker sinks
   (logger, clickhouse/outbox adapters) — never write exposure rows by hand.

3. **Core DI binds only `AbTesting` + `AssignmentStrategy`.** Never bind
   `ExperimentProvider`, `ExposureTracker` or `ConversionTracker` in the
   package's `config/di.php` — exactly one source (the app or a backend such
   as `yii3-ab-testing-db`) owns each key, or `yiisoft/config` throws
   `Duplicate key`.

4. **Targeting excludes, it does not reweight.** A subject that fails the
   experiment's `TargetingRule` gets `fallbackVariant` with
   `isFallback = true`, `isTargetingMismatch = true`. `forcedVariant`
   bypasses targeting; a disabled experiment is checked before targeting.

5. **Contracts that throw.** Experiment/variant names must match
   `/^[a-z][a-z0-9_-]*$/`; `fallbackVariant` must be in `variants`; total
   weight must be > 0; a forced variant must be in the variant list; unknown
   experiment → `InvalidExperimentException`; a conversion goal must contain a
   non-whitespace character. 64-bit PHP only.

## Canonical usage

```php
$ab = new AbTesting(
    provider: new ConfigExperimentProvider(config: [
        'checkout-button' => [
            'enabled' => true,
            'salt' => 'checkout-v1',
            'fallbackVariant' => 'control',
            'variants' => ['control' => 50, 'green' => 50],
        ],
    ]),
    strategy: new WeightedHashAssignmentStrategy(),
);

$assignment = $ab->assign(experiment: 'checkout-button', subjectId: $userId);
$assignment->variant;                        // 'control' or 'green'
$ab->trackExposure($assignment);             // explicit, never automatic
$ab->trackConversion($assignment, goal: 'purchase');
```

## Full API

The complete reference — `Assignment` flags, `AssignmentContext`, targeting
rule classes, tracker sinks and composites, `ExperimentRegistry`, Yii3 DI
wiring — ships with the package: read
`vendor/rasuvaeff/yii3-ab-testing/llms.txt` before guessing a method name.
