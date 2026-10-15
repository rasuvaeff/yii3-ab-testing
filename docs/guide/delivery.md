---
title: Two paths, one row
description: How exposure and conversion events reach analytics — the durable outbox path or log shipping through PSR-3 — and why both produce identical rows and neither writes from the request.
---

# Two paths, one row

Two delivery paths are supported. Both serialize through
`CanonicalEventSerializer`, and both land as the same rows in the same schema v2
tables.

| Path | How | Trade-off |
|---|---|---|
| **Durable** | `yii3-ab-testing-outbox` → `yii3-outbox-db` → worker → ClickHouse | survives an analytics outage; costs a table and a running worker |
| **Log shipping** | core `Logger*` sinks → Vector / Fluent Bit / Kafka → ClickHouse | no worker and no request-time network call; delivery is the collector's job |

Pick durable when losing events is unacceptable and you already run workers.
Pick log shipping when you already run a log pipeline and would rather not add
a queue.

## Not from the request path

**Do not write to analytics storage while serving the request.** Under PHP-FPM
a per-request insert means many tiny writes and a network call inside the
user's latency, and an in-memory buffer never fills because the worker does not
outlive the request. That is why the direct ClickHouse writer was removed from
`yii3-ab-testing-clickhouse` in 2.0.

## Log shipping

The core ships two PSR-3 sinks. Each event becomes one structured log record
whose `event` key holds exactly the canonical row:

```json
{"level":"info","message":"A/B test exposure","event":{"v":2,"event_id":"…","occurred_at":"2026-08-01 10:00:00.123","experiment":"checkout_button","variant":"b","subject_id":"…","decision_reason":"assigned","assignment_source":"computed","experiment_revision":"db:7","environment":"production","dimensions":"{\"country\":\"RU\"}"}}
```

```php
use Psr\Log\LoggerInterface;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;

return [
    ExposureTracker::class => static fn (LoggerInterface $logger): ExposureTracker
        => new LoggerExposureTracker($logger),
];
```

A runnable collector configuration is in the core's
[`examples/vector.toml`](https://github.com/rasuvaeff/yii3-ab-testing/blob/master/examples/vector.toml).

## Durable

`OutboxExposureTracker` and `OutboxConversionTracker` record each event as an
`ab.exposure` / `ab.conversion` message in the transactional outbox, so it can
commit in the same database transaction as your business write. A worker drains
it:

```bash
./yii outbox:clickhouse:export
```

`AbTestingClickHouseRoutes::map()` routes both message types into the tables
and column order declared by `AnalyticsSchemaV2` — it does not restate them.
The tracker passes the event's own id to the outbox as the message id, so the
exporter fills `event_id` with the same value whichever field it reads.

The aggregate id written to the outbox is pseudonymous:
`PseudonymousAggregateIdStrategy` derives it with HMAC-SHA-256 from an
application secret and never copies the raw `subject_id` into that column.

Without the worker nothing reaches analytics, and every HTTP response is still
`200` — assert delivered row counts, not status codes.

## Both at once

One source must own each tracker key. To fan out, compose in your own config:

```php
use Rasuvaeff\Yii3AbTesting\CompositeExposureTracker;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;

return [
    ExposureTracker::class => static fn (): ExposureTracker => new CompositeExposureTracker(
        new OutboxExposureTracker(/* … */),
        new LoggerExposureTracker(/* … */),
    ),
];
```

## Buffering and duplicates

Trackers that buffer implement `FlushableTracker`; call `flush()` once at the
end of the request. The composites implement it too and forward the flush to
every flushable inner tracker.

`DeduplicatingExposureTracker` emits at most one exposure per experiment,
subject and configuration within a request. It holds request state: bind it
request-scoped, or call `reset()` at the request boundary in a long-running
worker.

Delivery is at-least-once on either path. Duplicates are expected and collapse
in storage on `event_id` — see [Reading results](/guide/analytics).
