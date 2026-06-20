# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
