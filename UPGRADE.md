# Upgrade guide

## 1.x → 2.0

Version 2.0 introduces the versioned analytics event contract (schema v2). The
assignment algorithm is untouched: bucketing is still
`sha256(salt . ':' . subjectId)`, so **no visitor changes variant** because of
this upgrade.

What changes is what leaves the process. In 1.x each sink built its own
representation, and fields were lost differently on each path: no delivery path
carried `isTargetingMismatch` or `configurationId` at all, and context attributes
were dropped at the export boundary. In 2.0 the facade mints one event and every
sink transports it unchanged.

### Trackers take an event, not an assignment

```php
// 1.x
public function trackExposure(Assignment $assignment): void;
public function trackConversion(Assignment $assignment, string $goal): void;

// 2.0
public function trackExposure(ExposureEvent $event): void;
public function trackConversion(ConversionEvent $event): void;
```

If you wrote a custom tracker, change the signature and read the fields from the
event. Everything the assignment carried is on the event, plus `eventId`,
`occurredAt`, `experimentRevision` and `dimensions`.

Use `CanonicalEventSerializer` rather than assembling your own array — it is the
format the whole pipeline agrees on:

```php
$row = (new CanonicalEventSerializer())->exposure($event);
```

### Assignment: four booleans became two enums

| 1.x | 2.0 |
|---|---|
| `$assignment->isForced` | `$assignment->isForced()` or `reason === DecisionReason::Forced` |
| `$assignment->isFallback` | `$assignment->isFallback()` |
| `$assignment->isTargetingMismatch` | `$assignment->isTargetingMismatch()` |
| `$assignment->isSticky` | `$assignment->isSticky()` or `source === AssignmentSource::Store` |

**These are now methods, not properties** — `$assignment->isForced` raises a
warning and evaluates to null, which is easy to miss. Search your codebase for
the four names.

Constructing an `Assignment` directly (custom resolvers and stores do) changes
too:

```php
// 1.x
new Assignment(experiment: 'exp', variant: 'b', subjectId: 'u1', isSticky: true);

// 2.0
new Assignment(
    experiment: 'exp',
    variant: 'b',
    subjectId: 'u1',
    source: AssignmentSource::Store,
);
```

A fallback now states *why*: `DecisionReason::FallbackDisabled` for the kill
switch, `DecisionReason::FallbackTargetingMismatch` for a targeting miss. In 1.x
both were `isFallback: true` and indistinguishable downstream — that was the
defect this release closes.

**If you construct `Assignment` positionally, read this twice.** The parameter
list changed shape, so old positional calls now land on different parameters:

| Position | 1.x | 2.0 |
|---|---|---|
| 4 | `bool $isForced` | `DecisionReason $reason` |
| 5 | `bool $isFallback` | `AssignmentSource $source` |
| 6 | `?AssignmentContext $context` | `?AssignmentContext $context` |
| 7 | `bool $isSticky` | `?string $configurationId` |
| 8 | `bool $isTargetingMismatch` | — |

Positions 4, 5 and 7 fail loudly with a `TypeError`, which is what you want. Use
named arguments — the whole codebase does, precisely so that a reordering is a
compile-time problem rather than a silent one.

### Tracking methods return the event

```php
$exposure = $ab->trackExposure($assignment);        // ExposureEvent
$conversion = $ab->trackConversion($assignment, goal: 'purchase'); // ConversionEvent
```

Existing calls keep working — the return value is additive. Use it when you need
the event identity, typically `$exposure->receipt()` for a conversion in a later
request.

### Empty-goal exception message changed

The goal is now validated when the event is constructed:

```text
1.x: Conversion goal must not be empty
2.0: Event field "goal" must not be empty
```

The exception class is still `InvalidArgumentException`. Update tests that assert
the message.

### `AnalyticsContextPolicyInterface` moved into core

It lived in `yii3-ab-testing-outbox` and applied only to the durable path.
It is now `Rasuvaeff\Yii3AbTesting\AnalyticsContextPolicy`, so every delivery
path applies the same allow-list.

```php
// 1.x
use Rasuvaeff\Yii3AbTestingOutbox\AnalyticsContextPolicyInterface;
use Rasuvaeff\Yii3AbTestingOutbox\AllowListAnalyticsContextPolicy;

// 2.0
use Rasuvaeff\Yii3AbTesting\AnalyticsContextPolicy;
use Rasuvaeff\Yii3AbTesting\AllowListAnalyticsContextPolicy;
```

The constructor arguments and behaviour are unchanged. If you wrote a custom
policy, change the interface it implements and the namespace it imports.

**Pass the policy to the facade**, otherwise no dimensions are recorded: the
default allows nothing, by design.

```php
$ab = new AbTesting(
    provider: $provider,
    strategy: new WeightedHashAssignmentStrategy(),
    contextPolicy: new AllowListAnalyticsContextPolicy(allowedAttributes: ['country']),
);
```

### Logger sinks emit a different shape

`LoggerExposureTracker` and `LoggerConversionTracker` used to spread fields
across the PSR-3 context. They now write the canonical row under a single `event`
key:

```php
// 1.x context
['experiment' => …, 'variant' => …, 'isForced' => false, 'attributes' => [...]]

// 2.0 context
['event' => ['v' => 2, 'event_id' => …, 'decision_reason' => 'assigned', …]]
```

Log-based dashboards and collector configs that read the old keys must be
updated. This shape is what makes the logger sinks a first-class delivery path —
see `examples/vector.toml`.

### New optional constructor arguments on `AbTesting`

`clock`, `eventIds` and `contextPolicy` were added with working defaults, so
existing wiring keeps resolving. Override them to inject a fixed clock in tests,
to mint identifiers with `symfony/uid` or `ramsey/uuid`, or to allow-list
dimensions.

### No action needed for

- experiment definitions, `ConfigExperimentProvider` and targeting rules;
- `ExperimentRegistry`, `AssignmentStore` and `AssignmentResolver`;
- bucketing, salts and weights — assignment results are unchanged;
- `config/di.php` wiring of `AbTesting` and `AssignmentStrategy`.
