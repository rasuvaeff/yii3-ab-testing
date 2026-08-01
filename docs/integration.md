# Integration matrix

Every package in the family has a good isolated example. What this document adds
is the answer to *"how do I assemble **my** combination"* — the question that
sits between understanding the idea and running it in production.

Read it once, pick one option per axis, and the rest follows.

---

## Axis 1 — where experiment definitions live

| Option | Install | When |
|---|---|---|
| PHP config | core only | definitions change with a deploy anyway |
| Database | `yii3-ab-testing-db` | you need a kill switch and reweights without a deploy |

**Exactly one source may bind `ExperimentProvider`.** Installing the db backend
*and* binding `ConfigExperimentProvider` in your app is a `yiisoft/config`
`Duplicate key` error, by design — two sources of experiment truth is the bug,
not the error message.

```php
// config-only: the application binds it
ExperimentProvider::class => static fn (): ExperimentProvider
    => new ConfigExperimentProvider($params['experiments']),
```

With the db backend, bind nothing: the package owns the key.

---

## Axis 2 — how events reach analytics

| Option | Install | Trade-off |
|---|---|---|
| **Durable** | `yii3-ab-testing-outbox` + `yii3-outbox-db` + a worker | survives an analytics outage; costs a table and a running worker |
| **Log shipping** | core's `Logger*` sinks + Vector/Fluent Bit | no worker, no request-time network call; delivery is the collector's problem |

Both produce **the same rows** in the same schema v2 tables, owned by
`yii3-ab-testing-clickhouse`.

**Do not write to analytics storage from the request path.** Under PHP-FPM a
per-request insert means many tiny writes plus a network call inside the user's
latency; that is why the direct ClickHouse writer was removed in 2.0.

Pick durable when losing events is unacceptable and you already run workers.
Pick log shipping when you already run a log pipeline and would rather not add
a queue.

---

## Axis 3 — who the subject is

| Option | How | When |
|---|---|---|
| Anonymous cookie | `SubjectIdMiddleware` (`yii3-ab-testing-web`) | public traffic, no login required |
| Authenticated id | your auth middleware writes the request attribute | logged-in users; the cookie branch is never taken |
| Hybrid | both, plus `AnonymousToAuthenticatedStrategy` | visitors convert from anonymous to logged-in mid-experiment |

The hybrid case has a trap worth naming: without a migration strategy a visitor
assigned anonymously gets a *different* variant after logging in, because the
subject id changed. `MigrateAssignments` carries the assignment across.

---

## Axis 4 — keeping the variant stable

| Option | Install | Survives |
|---|---|---|
| Nothing | — | nothing: changing weights re-buckets everyone |
| Signed cookie | `yii3-ab-testing-web` | reweights; **not** a cookie clear or a new device |
| Database | `yii3-ab-testing-db` (`DbAssignmentStore`) | reweights, devices, cookie clears |
| Your own | implement `ConfigurationAwareAssignmentStore` | — |

Assignment is deterministic in `(salt, subjectId)`, so a subject keeps their
variant **as long as the weights hold**. Change the weights and bucket
boundaries move. If your experiment will ever be reweighted mid-flight — and it
will — a store is not optional.

---

## Axis 5 — several sinks at once

One source must own each tracker key. To fan out, compose in your own config:

```php
ExposureTracker::class => static fn (): ExposureTracker => new CompositeExposureTracker(
    new OutboxExposureTracker(/* … */),
    new LoggerExposureTracker(/* … */),
),
```

Installing two packages that each bind `ExposureTracker` is a `Duplicate key`
error. Since 2.0 `yii3-ab-testing-clickhouse` binds neither, so that particular
collision is gone.

---

## Axis 6 — SSR or SPA

In SSR the server knows it rendered the variant. In a SPA **only the client
does**, and that changes one thing fundamentally.

| | SSR | SPA / headless |
|---|---|---|
| Identity | cookie | request attribute from the token, or a client id in a header |
| Cross-origin | n/a | `SameSite=None` + `Secure` + CORS credentials |
| Stickiness | cookie store | `DbAssignmentStore` if cookies are out |
| **Exposure** | server-side, on render | **a call the client makes** |

Never track exposure on the endpoint that returns assignments: that counts
prefetches, routes the visitor never reached, and every repeat navigation.

Three endpoints, and they belong in your application — routing, auth and rate
limiting are not this library's business:

```
GET  /ab/assignments   → variants + a signed receipt, no tracking
POST /ab/exposure      → the client rendered it
POST /ab/conversion    → the goal happened
```

The receipt must be signed (`SignedReceiptCodec`). Re-resolving server-side is
not a substitute: after a reweight it returns the variant the visitor *would*
get now, not the one they saw.

Runnable: `vendor/rasuvaeff/yii3-ab-testing-web/examples/spa-endpoints.php`.

---

## Axis 7 — operations

**Migrations.** The documented `setSourceNamespaces()` registration silently
finds nothing while the core is installed — `migrate:up` prints "up-to-date",
exits 0 and creates no tables. Apply them through `Injector`; see each `-db`
package's `UPGRADE.md`.

**The worker** (durable path only) drains the outbox:
`outbox:clickhouse:export`. Without it, events accumulate and nothing reaches
analytics — the smoke test in the reference app asserts delivered row counts
precisely because HTTP 200s prove nothing here.

**Retention** is opt-in SQL in `yii3-ab-testing-clickhouse/retention/`, never
applied automatically.

**Erasure.** `subject_id` is personal data when it is a user id.
`DbAssignmentStore::forget($subjectId)` erases a subject across experiments.

---

## Axis 8 — reading the results

```sql
SELECT variant, uniqExact(subject_id) AS subjects
FROM ab_exposures_v2 FINAL
WHERE experiment = 'checkout_button' AND decision_reason = 'assigned'
GROUP BY variant
```

Two non-negotiables:

- **`FINAL`.** Deduplication happens on merge, not on insert, and delivery is
  at-least-once. Without it a retried delivery is counted twice.
- **`decision_reason = 'assigned'`.** Forced (QA) traffic and both fallback
  kinds are not experiment participation.

Attribution rules — the window, repeated conversions, exclusions — are fixed in
the core (`AttributionWindow`, `RepeatedConversionPolicy`) so every reporting
backend answers the same way.

---

## Version compatibility

| core | web | db | outbox | clickhouse |
|---|---|---|---|---|
| 2.x | 2.x | 3.x | 2.x | 2.x |
| 1.6 | 1.2 | 2.1 | 1.3 | 1.2 |

Mixing a 2.x core with 1.x adapters is prevented by Composer constraints. Each
package's `UPGRADE.md` covers its own migration.
