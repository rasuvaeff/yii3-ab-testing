---
title: Worker runtimes
description: RoadRunner and Swoole keep services alive between requests — how the StateResetter hook makes a kill switch take effect without a worker restart, and which services hold request state.
---

# Worker runtimes

Under PHP-FPM every request builds a fresh container, so nothing here applies.
RoadRunner, Swoole and similar runtimes keep services alive across requests,
and two parts of the family hold state.

## The experiment set

`ExperimentRegistry` is lazy: the first assignment asks the
`ExperimentProvider` for the experiments and memoizes the answer. The registry
lives inside the `AbTesting` service — which, in a worker, outlives the request.

Without a reset, the set loaded by the first request would be served for the
worker's whole lifetime: an experiment disabled in the database would keep
running until the next deploy.

The core's `config/di.php` therefore registers a `reset` hook for `yiisoft/di`'s
`StateResetter`. Runtimes that reset container state between requests clear the
memo, the next request re-reads the provider, and a kill switch flipped in the
source takes effect without restarting workers.

Outside a container, call it yourself at the request boundary:

```php
$ab->getRegistry()->reset();
```

With `yii3-ab-testing-db`, every request re-reading the database is what the
`CachedExperimentProvider` (PSR-16) and `LastKnownGoodExperimentProvider`
decorators are for — the reset clears the in-process memo, not your cache.

## Request-scoped services

| Service | State | In a worker |
|---|---|---|
| `DeduplicatingExposureTracker` | experiments already exposed in this request | bind request-scoped, or call `reset()` per request |
| `StickyAssignmentMiddleware` | builds its cookie store per request | safe — the store is created inside `process()` |
| Buffering trackers (`FlushableTracker`) | unflushed events | call `flush()` at the end of every request |

A deduplicating tracker that is never reset turns "once per request" into "once
per worker": a returning visitor's later exposures are silently dropped, and the
exposure table undercounts for as long as the worker lives.
