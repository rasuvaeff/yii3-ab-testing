# AGENTS.md — yii3-ab-testing

Guidance for AI agents working on this package. Read before changing code.

## What this is

Deterministic A/B testing for Yii3. Stateless assignment by `subjectId`,
weighted variants, forced variant for QA, explicit exposure/conversion tracking.

Namespace: `Rasuvaeff\Yii3AbTesting`.

Public API: `AbTesting` (facade), `Assignment`, `AssignmentContext`,
`AssignmentStrategy`, `WeightedHashAssignmentStrategy`, `Experiment`,
`ExperimentProvider`, `ConfigExperimentProvider`, `ExperimentRegistry`,
`ExposureTracker`, `ConversionTracker`, `FlushableTracker` (buffered-sink flush
contract; composites propagate it), `NullExposureTracker`,
`NullConversionTracker`, `LoggerExposureTracker`, `LoggerConversionTracker`
(built-in PSR-3 sinks), `CompositeExposureTracker`, `CompositeConversionTracker`,
`AssignmentStore` (sticky-variant contract; implementations ship in adapters).
`AssignmentResolver` (implemented by `AbTesting` and sticky adapters),
`TargetingRuleCodec` / `BuiltInTargetingRuleCodec` / `TargetingRuleCodecRegistry`
(shared config/DB targeting representation), and `DeduplicatingExposureTracker`
(request-scoped wrapper).

Event contract v2 (added in 2.0): `ExposureEvent`, `ConversionEvent`,
`DecisionReason`, `AssignmentSource`, `AssignmentReceipt`, `EventSerializer` /
`CanonicalEventSerializer`, `EventIdGenerator` with `Uuid7EventIdGenerator`
(default), `SymfonyUidEventIdGenerator` and `RamseyUuidEventIdGenerator`,
`AnalyticsContextPolicy` / `AllowListAnalyticsContextPolicy`, `SystemClock`,
`AttributionWindow`, `RepeatedConversionPolicy` and
`ConfigurationAwareAssignmentStore`.

`ConfigurationAwareAssignmentStore` moved here from `yii3-ab-testing-web` in 2.0:
both the cookie store and the database store implement it, and sibling adapters
must not depend on each other to share a contract.

**The row shapes in `EventSerializer` are public API.** Adapters import them with
`@psalm-import-type`, so renaming a key breaks their static analysis — which is
the point: that is how a field silently dropped at a package boundary becomes a
build failure. `roave/backward-compatibility-check` cannot see docblock aliases,
so treat a change to `AbExposureRow` / `AbConversionRow` exactly like a changed
signature: major releases only.

DI wiring (mirror of `yii3-feature-flags`): core `config/di.php` binds **only**
`AbTesting` (facade) and `AssignmentStrategy` (the single
`WeightedHashAssignmentStrategy`). It must NOT bind:

- `ExperimentProvider` — the experiment **source**, required by `AbTesting`'s
  constructor (no default). Owned by exactly one source: the application
  (`ConfigExperimentProvider` from params) or a storage backend
  (`yii3-ab-testing-db`). `AbTesting` builds `ExperimentRegistry` internally from
  the injected provider — `ExperimentRegistry` is not a DI key.
- `ExposureTracker` / `ConversionTracker` — the event **sinks**, optional. Default
  to no-op `Null*` via constructor defaults. Built-in `Logger*` (PSR-3) sinks ship
  in core but are not bound; a tracker adapter (`-clickhouse`) or the app binds them.

Two vendor packages binding the same key (`ExperimentProvider` or a tracker) in
the `di` group trigger a `yiisoft/config` `Duplicate key` error — by design.
See `yii3-package-plans/yii3-ab-testing-ecosystem.md`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Hash stability.** `sha256(salt . ':' . subjectId)`, first 8 hex → 32-bit
   bucket. This is bucketing algorithm v1 and must not change in a patch or minor
   release. It must match `yii3-feature-flags` for future bridge compatibility.
4. **assign()/is() are pure.** No side effects, no auto-tracking.
5. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library).
`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Mutation testing

`minMsi` is **99, not 100, and there are no ignored mutators** — the threshold is
honest rather than propped up by suppressions. Exactly three mutants survive out
of 364 generated, and all three are equivalent under PHP semantics, so no test
can kill them:

| Where | Mutation | Why it cannot be killed |
|---|---|---|
| `Uuid7EventIdGenerator::generate` | `(int)` cast dropped | `pack('J', $x)` coerces the numeric string exactly as the cast does |
| `Uuid7EventIdGenerator::generate` | `substr(pack('J', …), 2, 6)` → `2, 7` | the packed string is eight bytes, so asking for seven from offset two still returns six |
| `AbTesting::trackConversion` | `$exposure?->eventId` → `$exposure->eventId` | reading a property on null is a warning that evaluates to null, not an error |

The null-safe operator and the cast stay: they express intent, and the warning in
the third case is a real (if minor) production defect.

If a change makes these three disappear, raise `minMsi` back to 100. If a change
adds a **fourth** escaped mutant, the gate fails at 99 — that is the point.
Do not ignore mutators to get past it; strengthen the assertion instead.

Two assertions exist purely to kill subtle mutants in the hand-rolled UUIDv7 and
must not be weakened: `layoutFollowsRfc9562` checks the exact bit layout, and
`versionAndVariantBytesAreBuiltFromTheirOwnRandomByte` catches a neighbouring
byte index, which keeps both the format and the entropy valid and shows up only
as perfect correlation between two byte's random bits.

A third assertion is load-bearing for the same reason:
`ExposureEventTest::preservesEveryDimensionWhenMultipleAreGiven` passes three
`dimensions` entries and asserts all three come back unchanged. `EventFields`
is `@internal` and was previously named in no test's `#[Covers(...)]`, so
Infection generated zero mutants for it (ER-003 in `docs/evolved-rules.md`) —
every existing test only ever passed a single-entry `dimensions` array. Once
`EventFields::class` was added to `#[Covers(...)]` on
`ConversionEventTest`/`ExposureEventTest`/`AssignmentReceiptTest`, Infection's
`ArrayOneItem` mutator on `EventFields::requireDimensions` (line 56, the
`return $validated;`) survived: a mutant that silently truncates
`$validated` to its first entry when there's more than one is
indistinguishable from correct behaviour if no test ever supplies more than
one dimension. Do not collapse that test back to a single-key array.

## Invariants & gotchas

- Experiment/variant name regex: `/^[a-z][a-z0-9_-]*\z/`.
- `fallbackVariant` must exist in `variants`. Total weight > 0 — `Experiment`
  validates it, and `WeightedHashAssignmentStrategy` independently throws
  `InvalidArgumentException` when called directly with total weight <= 0.
- `ExperimentRegistry` is lazy: the provider is queried on first access and
  memoized; `reset()` drops the memo. Core `config/di.php` registers a `reset`
  hook (yiisoft/di `StateResetter`) so worker runtimes (RoadRunner, Swoole)
  re-read the provider per request — without it the enabled kill switch would be
  frozen for the worker's lifetime.
- `Assignment::isSticky` marks an assignment served from an `AssignmentStore`
  (set by sticky resolvers in adapters); `assign()` itself never sets it.
- 64-bit PHP only: the 8-hex-digit bucket exceeds `PHP_INT_MAX` on 32-bit.
- Disabled experiment returns `fallbackVariant` with `isFallback = true`.
- Forced variant must be in experiment's variant list.
- Variants sorted by key before cumulative weight calculation.
- `assign()` and `is()` never call trackers. Exposure via explicit `trackExposure()`.
- `configurationId` is a string definition identity, separate from an integer DB
  optimistic-lock revision. Config definitions use a canonical SHA-256 hash;
  runtime providers may supply a revision-derived string. Propagate it to every
  `Assignment`, including forced and fallback results.
- `DeduplicatingExposureTracker` is stateful: bind it request-scoped or call
  `reset()` between requests. Its key is experiment + subject + configuration ID.
- `AbTesting::trackConversion()` rejects empty and whitespace-only goals before
  calling the configured tracker. It does not normalize valid goals.
- `AssignmentContext` (optional `assign()` arg) flows into the returned `Assignment`
  for tracker attribution (environment/attributes). In v1 it does NOT affect variant
  selection — targeting is deferred. Keep it on `assign()` only (`is()` returns bool,
  so a context arg there would yield an undetectable mutant under `minMsi 100`).
- Salt is mandatory. Changing salt = full re-assignment.
- Changing weights or adding/removing variants shifts bucket boundaries.
- `config/di.php`: core binds ONLY `AbTesting` + `AssignmentStrategy`. Never bind
  `ExperimentProvider`, `ExposureTracker` or `ConversionTracker` — one source owns
  each (app or backend), else `yiisoft/config` throws `Duplicate key`. `AbTesting`
  requires an `ExperimentProvider` (no default), so without an app/backend binding
  it does not resolve — that is intentional (mirrors `FlagProvider`). This file is
  not covered by cs/psalm/test — verify changes with a real `yiisoft/di`
  resolution harness, not the build gate.

- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.

## When you finish

- Update `README.md` and `README.ru.md` together (and `examples/` if usage
  changed); update `CHANGELOG.md` when releasing.
- Re-run `composer build`; if the change affects the public API or release
  process, also run `make release-check`. Paste the output.
