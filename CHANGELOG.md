# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.1.0 — unreleased

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
