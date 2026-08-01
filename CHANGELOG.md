# Changelog

## 2.0.0 — 2026-08-01

### Added

- Versioned analytics event contract: `ExposureEvent`, `ConversionEvent`,
  `DecisionReason`, `AssignmentSource` and `AssignmentReceipt`.
- `EventSerializer` with `CanonicalEventSerializer` — one wire format shared by
  every delivery path, with the row shapes published as importable Psalm types.
- `EventIdGenerator` with three implementations: `Uuid7EventIdGenerator`
  (default, no dependencies), `SymfonyUidEventIdGenerator` and
  `RamseyUuidEventIdGenerator`.
- `AttributionWindow` and `RepeatedConversionPolicy` — the reporting rules that
  decide what conversion numbers mean.
- `SystemClock`, and `clock` / `eventIds` / `contextPolicy` arguments on
  `AbTesting`.
- `AbTesting::trackConversionForReceipt()` for a conversion in a later request.
- `fixtures/golden-event-v2.json`, shipped so adapters can assert the same rows.
- `ConfigurationAwareAssignmentStore`, moved here from `yii3-ab-testing-web` so
  the cookie store and the database store can share it without depending on
  each other.

### Changed

- **Breaking.** `ExposureTracker` and `ConversionTracker` take an event instead
  of an `Assignment` and a goal.
- **Breaking.** `Assignment` replaces `isForced`, `isFallback`, `isSticky` and
  `isTargetingMismatch` properties with a `reason` / `source` pair and
  same-named methods.
- **Breaking.** `AnalyticsContextPolicy` (was
  `AnalyticsContextPolicyInterface` in `yii3-ab-testing-outbox`) moved into the
  core, so every delivery path applies one allow-list.
- **Breaking.** Logger sinks emit the canonical row under an `event` key,
  making them the log-shipping delivery path.
- **Breaking.** An empty conversion goal now reports
  `Event field "goal" must not be empty`.
- `trackExposure()` and `trackConversion()` return the recorded event.

See [UPGRADE.md](UPGRADE.md) for the migration steps.

## 1.6.0 — 2026-08-01

- Decode targeting trees in `ConfigExperimentProvider` with the extensible,
  bidirectional `TargetingRuleCodec` / `TargetingRuleCodecRegistry` shared by
  config and storage backends.
- Add the `AssignmentResolver` contract, implement it in `AbTesting`, and add
  context-aware boolean checks through `isWithContext()`.
- Add optional string `configurationId` to `Experiment` and `Assignment`; config
  experiments receive a deterministic canonical definition hash.
- Add request-scoped `DeduplicatingExposureTracker`, keyed by experiment,
  subject and configuration identity, with an explicit worker `reset()` hook.

## 1.5.2 — 2026-07-29

- Reject empty and whitespace-only conversion goals in `AbTesting` before
  delegating to the configured tracker.
- Document bucketing algorithm v1 as stable across patch and minor releases.

## 1.5.1 — 2026-07-25

- Reject trailing newlines in experiment-name validation: anchor
  `Experiment::NAME_PATTERN` with `\z` instead of `$` (PCRE `$` matches before
  a trailing `\n`, which let `"<name>\n"` pass and reach bucketing/trackers).

## 1.5.0 — 2026-07-25

- Ship an AI agent skill (`resources/skills/rasuvaeff-yii3-ab-testing/SKILL.md` +
  `extra.skills` in composer.json): projects using the `llm/skills` Composer
  plugin get the skill synced into `.agents/skills/` automatically on install.
- Bump `rasuvaeff/property-testing` dev dependency to `^2.6`.
- Make property-based generator methods `public static` (rector's
  `RemoveUnusedPrivateMethodRector` would delete private ones — they are only
  called via reflection).

## 1.4.2 — 2026-06-30

- Add `/benchmarks` and `/Makefile` to `.gitattributes` export-ignore.

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.4.1 — 2026-06-27

- Migrate test suite from PHPUnit to Testo. Internal change, no public API impact.

## 1.4.0 — 2026-06-20

- `TargetingRule` interface + 4 implementations: `EnvironmentTargetingRule`,
  `AttributeTargetingRule`, `AndTargetingRule`, `OrTargetingRule`.
- `Experiment` now accepts optional `?TargetingRule $targeting = null`; subjects
  that don't match receive the fallback variant.
- `Assignment` gains `isTargetingMismatch: bool` (default false) to distinguish
  targeting-excluded subjects from disabled-experiment fallback.
- `AbTesting::assign()` checks targeting after the disabled-experiment guard and
  before calling the assignment strategy. `forcedVariant` bypasses targeting.

## 1.3.0 — 2026-06-11

- `LoggerExposureTracker` / `LoggerConversionTracker` — default zero-infra sinks that write each event as one structured PSR-3 log record (log level configurable). Folded in from the former `yii3-ab-testing-psr-logger` adapter. Core `config/di.php` does not bind them (one-source rule); bind them in app config. Adds `psr/log` as a runtime dependency.

## 1.2.0 — 2026-06-11

- `FlushableTracker` interface for buffered sinks; `CompositeExposureTracker` and `CompositeConversionTracker` implement it and propagate `flush()` to flushable inner trackers.
- `Assignment::isSticky` flag — set by sticky resolvers (adapters) when an assignment is served from an `AssignmentStore`.
- `ExperimentRegistry` is now lazy: the provider is queried on first access and memoized; new `reset()` re-reads it. Core `config/di.php` registers a `reset` hook for `yiisoft/di` `StateResetter`, so in worker runtimes (RoadRunner, Swoole) a kill switch flipped in the experiment source takes effect on the next request instead of after a worker restart.
- `WeightedHashAssignmentStrategy` throws `InvalidArgumentException` when called directly with a total variant weight of 0 (previously `DivisionByZeroError`); the `AssignmentStrategy` contract now documents the requirement.
- Documented the 64-bit PHP requirement of the hash bucketing.

## 1.1.0 — 2026-06-10

- `CompositeExposureTracker` and `CompositeConversionTracker` (variadic) to fan a single event out to several sinks; applications bind them in their own root-layer config.
- `AssignmentStore` interface (sticky variant) — a pure contract with no HTTP; cookie/session implementations ship in `yii3-ab-testing-web`. Sticky resolution is a separate layer; `assign()` stays pure.

## 1.0.0 — 2026-06-10

- `AbTesting` facade with `assign()`, `is()`, `trackExposure()`, `trackConversion()`.
- `Experiment` value object: name, enabled flag, salt, fallback variant, weighted variants.
- `ExperimentProvider` interface (experiment source) with `ConfigExperimentProvider` (static array); `AbTesting` builds `ExperimentRegistry` from the injected provider. Runtime/DB providers ship as adapter packages.
- `WeightedHashAssignmentStrategy` — deterministic SHA-256 bucketing, compatible with `yii3-feature-flags`.
- `Assignment` value object with `isForced` and `isFallback` flags.
- `AssignmentContext` (environment + attributes), optional arg to `assign()`, carried into `Assignment` for tracker attribution; does not affect variant selection (targeting deferred).
- `ExposureTracker` and `ConversionTracker` interfaces for custom tracking.
- `NullExposureTracker` and `NullConversionTracker` for stateless usage.
- `assign()` and `is()` are pure — no auto-tracking, no side effects.
- DB/ClickHouse trackers and bridge to `yii3-feature-flags` deferred to adapter packages.
