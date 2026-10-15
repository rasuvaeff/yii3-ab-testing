---
title: Attribution
description: The attribution window (7 days by default) and the repeated-conversion policy (FirstOnly vs All) — fixed in the core so every report counts the same way.
---

# Attribution

Reporting happens outside PHP, but the rules that decide what a conversion rate
*means* are fixed in the core, next to the events. A report built from the rows
follows them; two reports following them agree.

## Attribution window

A conversion counts for an exposure when it happens within the window after
that exposure.

```php
use Rasuvaeff\Yii3AbTesting\AttributionWindow;

AttributionWindow::default();        // 7 days
AttributionWindow::ofDays(14);
AttributionWindow::ofSeconds(3600);
```

A conversion that carries `exposure_event_id` (it was tracked with the
exposure, or from a receipt) is linked explicitly. Without one, attribution
falls back to the window around the subject's first exposure.

## Repeated conversions

```php
use Rasuvaeff\Yii3AbTesting\RepeatedConversionPolicy;

RepeatedConversionPolicy::FirstOnly; // default
RepeatedConversionPolicy::All;
```

| Policy | Counts | Use for |
|---|---|---|
| `FirstOnly` | one conversion per subject, goal, experiment and revision | conversion **rate** |
| `All` | every conversion | **value** metrics — revenue, items, sessions |

`FirstOnly` is the default because a conversion rate is a share of subjects:
one visitor converting ten times must not outweigh ten visitors converting once.

## What is excluded

Only `decision_reason = 'assigned'` is participation — forced (QA) traffic and
both kinds of fallback are recorded but never attributed. See
[Decision reason and source](/guide/decision).
