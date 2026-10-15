---
title: Events v2
description: ExposureEvent and ConversionEvent, the canonical flat row CanonicalEventSerializer produces, event ids as the pipeline's deduplication key, and the analytics allow-list.
---

# Events v2

`trackExposure()` and `trackConversion()` return the event they recorded — an
`ExposureEvent` or a `ConversionEvent`. Everything downstream (the outbox, the
log sink, ClickHouse) receives the same object, serialized the same way.

## The canonical row

`CanonicalEventSerializer` turns an event into one flat row. It is the single
source of the wire format: the defect this contract was introduced to close was
two delivery paths serializing the same event differently and dropping
different fields.

```json
{"v":2,"event_id":"0198f2c1-4d3a-7c9e-8b21-6f4a2d9e0c17","occurred_at":"2026-08-01 10:00:00.123","experiment":"checkout","variant":"green","subject_id":"user-1","decision_reason":"assigned","assignment_source":"computed","experiment_revision":"db:7","environment":"production","dimensions":"{\"country\":\"RU\"}"}
```

| Field | Exposure | Conversion | Notes |
|---|---|---|---|
| `v` | ✓ | ✓ | schema generation, always `2`; transport metadata |
| `event_id` | ✓ | ✓ | minted once by the core — the deduplication key |
| `occurred_at` | ✓ | ✓ | event time in UTC with milliseconds, not delivery time |
| `experiment`, `variant`, `subject_id` | ✓ | ✓ | |
| `goal` | | ✓ | non-blank; validated before any tracker runs |
| `decision_reason`, `assignment_source` | ✓ | ✓ | see [Decision reason and source](/guide/decision) |
| `experiment_revision` | ✓ | ✓ | the experiment's `configurationId`, empty when unknown |
| `environment` | ✓ | ✓ | from the assignment context, empty when none |
| `dimensions` | ✓ | ✓ | allow-listed context attributes, as a JSON **string** |
| `exposure_event_id` | | ✓ | the exposure this conversion belongs to, empty when unlinked |

Every value is a scalar. `dimensions` is a string rather than a nested object
because the outbox exporter rejects non-scalar payload fields, and the two
delivery paths must stay byte-identical.

## The row shape is public API

The two shapes are declared on `EventSerializer` as the psalm types
`AbExposureRow` and `AbConversionRow`, and the adapters import them with
`@psalm-import-type`. Renaming or dropping a key therefore fails the adapters'
static analysis — deliberately, because that is how a field lost at a package
boundary becomes a build failure instead of a silently empty column.

`roave/backward-compatibility-check` cannot see docblock types, so a change to
either shape is treated exactly like a changed method signature: major
releases only.

## Event identity

`EventIdGenerator` mints `event_id`. The default `Uuid7EventIdGenerator` has no
dependencies and sorts by time; adapters exist for the common libraries.

| Generator | Requires |
|---|---|
| `Uuid7EventIdGenerator` (default) | nothing |
| `SymfonyUidEventIdGenerator` | `symfony/uid` |
| `RamseyUuidEventIdGenerator` | `ramsey/uuid` |

The format is not part of the contract — the interface returns a string and the
column is a string. What is part of it: the identifier is minted **once**, by
the facade, and every retry downstream carries the same value. That is what lets
ClickHouse collapse a delivery repeated after an uncertain outcome.

## Analytics dimensions

Context attributes reach storage only through an `AnalyticsContextPolicy`,
configured on the `AbTesting` facade so that every delivery path applies one
allow-list. The default allows nothing — an attribute added for targeting cannot
leak into analytics by accident.

```php
use Rasuvaeff\Yii3AbTesting\AllowListAnalyticsContextPolicy;

new AllowListAnalyticsContextPolicy(
    allowedAttributes: ['country', 'plan', 'user'],
    renamedAttributes: ['country' => 'geo'],  // stored under another key
    redactedAttributes: ['user'],             // key kept, value replaced
);
```

Anything not listed is dropped, not redacted. Redaction keeps the key and stores
`[redacted]` as the value.

## Converting in a later request

Re-resolving the assignment at conversion time can return a different variant
than the visitor saw — a reweight or a salt change in between is enough. Carry
an `AssignmentReceipt` from the exposure instead:

```php
use Rasuvaeff\Yii3AbTesting\AssignmentReceipt;

$_SESSION['ab_receipt'] = $exposure->receipt()->toArray();

// a later request
$receipt = AssignmentReceipt::fromArray($_SESSION['ab_receipt']);
$ab->trackConversionForReceipt($receipt, goal: 'purchase', context: $context);
```

The receipt is deliberately small — no environment, no dimensions — because it
travels in size-capped cookies, and the conversion records the context of its
own request anyway. `fromArray()` re-validates every field. When the receipt
crosses an untrusted boundary (a SPA, a cookie), sign it with
`SignedReceiptCodec` from `yii3-ab-testing-web`.
