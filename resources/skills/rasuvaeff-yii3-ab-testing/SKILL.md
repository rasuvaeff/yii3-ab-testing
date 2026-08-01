---
name: rasuvaeff-yii3-ab-testing
description: >-
  Deterministic A/B testing for Yii3 with rasuvaeff/yii3-ab-testing —
  AbTesting facade, Experiment, Assignment, WeightedHashAssignmentStrategy,
  ConfigExperimentProvider, targeting rules, exposure/conversion events and
  tracker sinks. Use when writing, reviewing or debugging experiment
  assignment, variant bucketing, targeting, or exposure/conversion tracking
  in a project that has this package installed.
---

# rasuvaeff/yii3-ab-testing

Stateless, deterministic A/B testing: a subject id is hashed into a weighted
variant bucket; exposures and conversions are tracked explicitly as versioned
analytics events. Namespace `Rasuvaeff\Yii3AbTesting\`.

## Safety rules — verify these on every change

1. **Bucketing is deterministic.** Variant = hash of `salt . ':' . subjectId`
   (sha256, first 8 hex, 32-bit bucket). Same subject always gets the same
   variant. Changing the salt re-randomizes the whole experiment; changing
   weights or adding/removing variants shifts bucket boundaries. Never "tweak"
   a salt on a running experiment. This is bucketing algorithm v1; patch and
   minor releases must not change its hash, sorting or boundary rules.

2. **`assign()` / `is()` are pure — no auto-tracking.** Exposures and
   conversions are recorded only via explicit `trackExposure()` /
   `trackConversion()`. Auto-tracking would invent impressions on prefetch,
   on hidden branches and on repeated calls.

3. **Core DI binds only `AbTesting` + `AssignmentStrategy`.** Never bind
   `ExperimentProvider`, `ExposureTracker` or `ConversionTracker` in the
   package's `config/di.php` — exactly one source (the app or a backend such
   as `yii3-ab-testing-db`) owns each key, or `yiisoft/config` throws
   `Duplicate key`.

4. **Targeting excludes, it does not reweight.** A subject that fails the
   experiment's `TargetingRule` gets `fallbackVariant` with
   `reason = DecisionReason::FallbackTargetingMismatch`. `forcedVariant`
   bypasses targeting; a disabled experiment is checked before targeting and
   yields `DecisionReason::FallbackDisabled`.

5. **Never write to analytics storage from the request path.** Under PHP-FPM a
   per-request insert means many tiny writes plus a network call inside the
   user's latency. Two supported delivery paths: durable (outbox adapter and a
   worker) or log shipping (`Logger*` sinks plus a collector). The direct
   ClickHouse writer was removed in 2.0 for exactly this reason.

6. **Contracts that throw.** Experiment/variant names must match
   `/^[a-z][a-z0-9_-]*\z/`; `fallbackVariant` must be in `variants`; total
   weight must be > 0; a forced variant must be in the variant list; unknown
   experiment → `InvalidExperimentException`; blank event fields (including a
   conversion goal) → `InvalidArgumentException`. 64-bit PHP only.

## Two axes, not four booleans (2.0)

`Assignment` and both event types carry `reason` and `source`, which answer
different questions. Do not collapse them.

- `DecisionReason`: `assigned`, `forced`, `fallback_disabled`,
  `fallback_targeting_mismatch`. Only `assigned` counts as participation.
- `AssignmentSource`: `computed`, `store` (served from an `AssignmentStore`).

`isForced()`, `isFallback()`, `isTargetingMismatch()` and `isSticky()` are
**methods**, not properties. In 1.x they were properties — reading them as
properties now raises a warning and evaluates to null.

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
    // Nothing reaches analytics unless allow-listed here.
    contextPolicy: new AllowListAnalyticsContextPolicy(allowedAttributes: ['country']),
);

$assignment = $ab->assign(experiment: 'checkout-button', subjectId: $userId);
$assignment->variant;                    // 'control' or 'green'

$exposure = $ab->trackExposure($assignment);                // ExposureEvent
$ab->trackConversion($assignment, goal: 'purchase', exposure: $exposure);

// Conversion in a LATER request: carry the receipt, never re-resolve —
// a reweight in between would otherwise attribute it to the wrong variant.
$receipt = $exposure->receipt();
$ab->trackConversionForReceipt($receipt, goal: 'purchase');
```

## Event contract

`eventId` and `occurredAt` are minted once, by the facade. Never regenerate
them downstream: `eventId` is the deduplication key of the whole pipeline, so a
retried delivery must carry the same value.

`CanonicalEventSerializer` is the single wire format. Its row shapes are
published as importable Psalm types (`AbExposureRow`, `AbConversionRow`) and are
public API — renaming a key breaks adapters' static analysis on purpose, and
`roave` cannot see docblock aliases, so change them only in a major.

Every value in a row is scalar and `dimensions` is a JSON **string**, not a
nested object: the outbox exporter rejects nested payload fields, and both
delivery paths must produce identical rows.

## Full API

The complete reference — event DTOs, `AssignmentReceipt`, id generators,
attribution contract, targeting rule classes, tracker composites,
`ExperimentRegistry`, Yii3 DI wiring — ships with the package: read
`vendor/rasuvaeff/yii3-ab-testing/llms.txt` before guessing a method name.
Upgrading from 1.x: `vendor/rasuvaeff/yii3-ab-testing/UPGRADE.md`.
