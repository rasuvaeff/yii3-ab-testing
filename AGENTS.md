# AGENTS.md — yii3-ab-testing

Guidance for AI agents working on this package. Read before changing code.

## What this is

Deterministic A/B testing for Yii3. Stateless assignment by `subjectId`,
weighted variants, forced variant for QA, explicit exposure/conversion tracking.

Namespace: `Rasuvaeff\Yii3AbTesting`.

Public API: `AbTesting` (facade), `Assignment`, `AssignmentContext`,
`AssignmentStrategy`, `WeightedHashAssignmentStrategy`, `Experiment`,
`ExperimentProvider`, `ConfigExperimentProvider`, `ExperimentRegistry`,
`ExposureTracker`, `ConversionTracker`, `NullExposureTracker`,
`NullConversionTracker`, `CompositeExposureTracker`, `CompositeConversionTracker`,
`AssignmentStore` (sticky-variant contract; implementations ship in adapters).

DI wiring (mirror of `yii3-feature-flags`): core `config/di.php` binds **only**
`AbTesting` (facade) and `AssignmentStrategy` (the single
`WeightedHashAssignmentStrategy`). It must NOT bind:

- `ExperimentProvider` — the experiment **source**, required by `AbTesting`'s
  constructor (no default). Owned by exactly one source: the application
  (`ConfigExperimentProvider` from params) or a storage backend
  (`yii3-ab-testing-db`). `AbTesting` builds `ExperimentRegistry` internally from
  the injected provider — `ExperimentRegistry` is not a DI key.
- `ExposureTracker` / `ConversionTracker` — the event **sinks**, optional. Default
  to no-op `Null*` via constructor defaults; a tracker adapter (`-psr-logger`,
  `-clickhouse`, `-opentelemetry`) or the app binds them.

Two vendor packages binding the same key (`ExperimentProvider` or a tracker) in
the `di` group trigger a `yiisoft/config` `Duplicate key` error — by design.
See `yii3-package-plans/yii3-ab-testing-ecosystem.md`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Hash stability.** `sha256(salt . ':' . subjectId)`, first 8 hex → 32-bit
   bucket. Must match `yii3-feature-flags` for future bridge compatibility.
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

## Invariants & gotchas

- Experiment/variant name regex: `/^[a-z][a-z0-9_-]*$/`.
- `fallbackVariant` must exist in `variants`. Total weight > 0.
- Disabled experiment returns `fallbackVariant` with `isFallback = true`.
- Forced variant must be in experiment's variant list.
- Variants sorted by key before cumulative weight calculation.
- `assign()` and `is()` never call trackers. Exposure via explicit `trackExposure()`.
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
  not covered by cs/psalm/phpunit — verify changes with a real `yiisoft/di`
  resolution harness, not the build gate.

- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects the public API or release
  process, also run `make release-check`. Paste the output.
