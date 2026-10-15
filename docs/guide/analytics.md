---
title: Reading results
description: The schema v2 ClickHouse tables owned by yii3-ab-testing-clickhouse, how to create them, and the two rules every query must follow — FINAL and decision_reason = 'assigned'.
---

# Reading results

`yii3-ab-testing-clickhouse` owns the analytics schema. Both delivery paths
write into it; nothing in the family writes to it from PHP any more.

## Tables

| Table | Constant | Rows |
|---|---|---|
| `ab_exposures_v2` | `AnalyticsSchemaV2::EXPOSURES_TABLE` | one per exposure event |
| `ab_conversions_v2` | `AnalyticsSchemaV2::CONVERSIONS_TABLE` | one per conversion event, with `goal` and `exposure_event_id` |

The columns are the canonical row from [Events v2](/guide/events) minus the
transport field `v`, plus `ingested_at`, which the table fills itself. Both
tables are `ReplacingMergeTree(ingested_at)`, partitioned by
`toYYYYMM(occurred_at)` and ordered by `(experiment, event_id)`.

`AnalyticsSchemaV2::EXPOSURE_COLUMNS` and `CONVERSION_COLUMNS` list the insert
columns **in order**. They exist so producers can check themselves against the
schema: an `INSERT` lists columns positionally, and a reordered producer would
write a variant into the subject column without any error.

## Creating them

```php
use Rasuvaeff\Yii3AbTestingClickHouse\SchemaMigrations;

(new SchemaMigrations(client: $clickHouseClient))->apply();
```

`apply()` runs the shipped migrations through the toolkit's
`ClickHouseMigrationRunner` and returns the names it applied — a second call
returns an empty list. It is explicit on purpose: creating analytics tables is
a deployment step, not a side effect of `composer install`. Pass other table
names to `apply()` to rename them; `SchemaMigrations::placeholders()` gives the
exact token map to an application that runs its own migration pipeline.

The v1 tables (`ab_exposures`, `ab_conversions`) are still created, so an
installation upgraded from 1.x keeps reading its history. The two generations
never share a table name and are not comparable row for row: v1 has no event
identity and its timestamp is ingestion time.

## Two non-negotiables

```sql
SELECT variant, uniqExact(subject_id) AS subjects
FROM ab_exposures_v2 FINAL
WHERE experiment = 'checkout-button'
  AND decision_reason = 'assigned'
GROUP BY variant
```

- **`FINAL`** (or `GROUP BY event_id`). `ReplacingMergeTree` collapses
  duplicates on merge, not on insert, and delivery is at-least-once. Without it
  a retried delivery is counted twice.
- **`decision_reason = 'assigned'`**. Forced QA traffic and both kinds of
  fallback are recorded, but they are not experiment participation.

Conversion queries follow the [attribution rules](/guide/attribution): link by
`exposure_event_id` where present, otherwise by the window, and count first
conversions only unless you are measuring a value.

## Retention

Opt-in TTL templates ship in the package's
[`retention/`](https://github.com/rasuvaeff/yii3-ab-testing-clickhouse/tree/master/retention)
directory. They are never applied automatically — an upgrade must never start
deleting analytics data. Review the SQL, then apply it through your deployment
process.

## Personal data

`subject_id` is personal data when it is a user id. On the request side, the
analytics allow-list keeps unlisted context attributes out of the rows
entirely, and `DbAssignmentStore::forget()` erases a subject's sticky pins.
Erasing already-delivered rows from ClickHouse is your retention process's job.
