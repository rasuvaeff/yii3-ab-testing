# rasuvaeff/yii3-ab-testing

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-ab-testing/php)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-ab-testing.svg)](https://github.com/rasuvaeff/yii3-ab-testing/blob/master/LICENSE.md)
[Русская версия](README.ru.md)

Deterministic A/B testing for Yii3 applications. Stateless assignment, weighted
variants, forced variant for QA, explicit exposure/conversion tracking.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference
> you can pass as context.
> Projects using the [llm/skills](https://github.com/roxblnfk/skills) Composer
> plugin also get this package's agent skill synced into `.agents/skills/`
> automatically on install.

> **Assembling a combination?** [docs/integration.md](docs/integration.md)
> walks the eight axes — where definitions live, how events reach analytics,
> who the subject is, stickiness, SSR vs SPA, operations, reading results.

## Requirements

- PHP 8.3+ (64-bit — the hash bucket exceeds `PHP_INT_MAX` on 32-bit builds)

## Installation

```bash
composer require rasuvaeff/yii3-ab-testing
```

## Usage

### Configure experiments

```php
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

$provider = new ConfigExperimentProvider(config: [
    'checkout-button' => [
        'enabled' => true,
        'salt' => 'checkout-v1',
        'fallbackVariant' => 'control',
        'variants' => ['control' => 50, 'green' => 50],
        'targeting' => [
            'type' => 'environment',
            'values' => ['production'],
        ],
    ],
]);

$ab = new AbTesting(
    provider: $provider,
    strategy: new WeightedHashAssignmentStrategy(),
);
```

Experiment definitions come from an `ExperimentProvider`. `ConfigExperimentProvider`
reads a static array; a storage backend (e.g. `yii3-ab-testing-db`) supplies a
database-backed provider so experiments can be toggled at runtime without a deploy.
Config definitions receive a deterministic `configurationId` hash. Runtime
providers may pass an explicit string ID (for example, a database revision) to
`Experiment`; every `Assignment` carries it for analytics and exposure deduplication.

### Assign variant

```php
$assignment = $ab->assign(experiment: 'checkout-button', subjectId: (string) $userId);

if ($assignment->isVariant('green')) {
    // Show green button.
}

// Quick check:
if ($ab->is(experiment: 'checkout-button', variant: 'green', subjectId: (string) $userId)) {
    // Variant-specific logic.
}
```

Assigning an experiment that is not defined throws
`Exception\InvalidExperimentException`; forcing a variant the experiment does not
have throws `Exception\InvalidVariantException`. The loaded experiment set is
inspectable via `$ab->getRegistry()` — an `ExperimentRegistry` with `get()`,
`has()`, `all()` and `reset()`. The registry is lazy: the `ExperimentProvider` is
queried on first access and memoized afterwards.

### Forced variant (QA)

```php
$assignment = $ab->assign(
    experiment: 'checkout-button',
    subjectId: (string) $userId,
    forcedVariant: 'green',
);
```

### Why this variant: decision reason and source

Every `Assignment` answers two independent questions.

| Question | Field | Values |
|---|---|---|
| Why this variant rather than the hash bucket? | `reason` | `assigned`, `forced`, `fallback_disabled`, `fallback_targeting_mismatch` |
| Where did the value come from? | `source` | `computed`, `store` |

They are separate on purpose: a sticky-served forced variant is a legitimate
combination, and a disabled experiment must stay distinguishable from a
targeting miss — with a single flag both look like "fallback".

```php
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\DecisionReason;

$assignment->reason === DecisionReason::FallbackTargetingMismatch;
$assignment->source === AssignmentSource::Store;

// Derived checks where the meaning is unambiguous:
$assignment->isForced();
$assignment->isFallback();
$assignment->isTargetingMismatch();
$assignment->isSticky();
```

Only `assigned` counts as experiment participation; reports exclude everything
else. `DecisionReason::isAnalyzable()` states that rule in code.

### Track exposure and conversion

```php
// assign() does NOT auto-track. Call explicitly:
$exposure = $ab->trackExposure($assignment);

// On conversion in the same request, link it to the exposure:
$ab->trackConversion($assignment, goal: 'purchase', exposure: $exposure);
```

Both methods return the event they recorded — an `ExposureEvent` or a
`ConversionEvent` carrying `eventId`, `occurredAt`, the decision, the experiment
revision, the environment and the allow-listed dimensions. The identifier is the
deduplication key of the whole pipeline: a delivery retried after an uncertain
outcome carries the same value, so storage collapses the duplicate.

The conversion goal must contain at least one non-whitespace character; an
invalid goal is rejected when the event is constructed, before any tracker runs.

### Conversion in a later request

Re-resolving the assignment at conversion time can return a different variant
than the visitor saw — a reweight or a salt change in between is enough. Carry a
receipt instead:

```php
$receipt = $exposure->receipt();
$_SESSION['ab_receipt'] = $receipt->toArray(); // or a signed cookie

// … a later request …

$receipt = AssignmentReceipt::fromArray($_SESSION['ab_receipt']);
$ab->trackConversionForReceipt($receipt, goal: 'purchase', context: $context);
```

`AssignmentReceipt` is deliberately small — it holds no environment or
dimensions, because it travels in size-capped cookies and a conversion records
the context of its own request anyway. `fromArray()` re-validates every field:
transport data is never trusted, and unknown enum values are rejected.

### Analytics dimensions

Context attributes reach storage only through an `AnalyticsContextPolicy`. The
default allows nothing, so an attribute added for targeting cannot leak into
analytics by accident.

```php
use Rasuvaeff\Yii3AbTesting\AllowListAnalyticsContextPolicy;

new AllowListAnalyticsContextPolicy(
    allowedAttributes: ['country', 'plan', 'user'],
    renamedAttributes: ['country' => 'geo'],  // stored column name
    redactedAttributes: ['user'],             // kept as a column, value masked
);
```

Anything not listed is dropped, not redacted. Redaction keeps the column and
replaces the value with `[redacted]`.

### Event identifiers

`EventIdGenerator` mints the event identity. The default `Uuid7EventIdGenerator`
has no dependencies, so the package works straight after `composer require`, and
UUIDv7 sorts by time — useful as a storage sort prefix. Adapters for the two
common libraries ship alongside it:

| Generator | Requires |
|---|---|
| `Uuid7EventIdGenerator` (default) | nothing |
| `SymfonyUidEventIdGenerator` | `symfony/uid` |
| `RamseyUuidEventIdGenerator` | `ramsey/uuid` |

Nothing is selected automatically — bind the one you want. The format is not part
of the contract: the interface returns a string and the analytics column is a
string, so ULIDs, snowflakes or your own keys are equally valid.

### Attribution contract

Reporting lives in the analytics package, but the rules that decide what the
numbers *mean* are fixed here so every backend answers the same way.

```php
use Rasuvaeff\Yii3AbTesting\AttributionWindow;
use Rasuvaeff\Yii3AbTesting\RepeatedConversionPolicy;

AttributionWindow::default();      // 7 days
AttributionWindow::ofDays(14);
RepeatedConversionPolicy::FirstOnly; // default: one per subject, goal, revision
RepeatedConversionPolicy::All;       // count every conversion (value metrics)
```

A conversion counts for an exposure when it happens within the window after it.
`FirstOnly` is the default because conversion rate is a share of subjects: one
visitor converting ten times must not outweigh ten visitors converting once.

### Assignment context (optional)

Pass an `AssignmentContext` to attribute metrics by environment/segment. It is
carried into the returned `Assignment` so trackers can read it. Context may
control targeting eligibility, but never changes the deterministic hash bucket.

```php
use Rasuvaeff\Yii3AbTesting\AssignmentContext;

$context = AssignmentContext::forEnvironment('production')
    ->withAttribute('country', 'DE');

$assignment = $ab->assign(
    experiment: 'checkout-button',
    subjectId: (string) $userId,
    context: $context,
);

$assignment->context?->getEnvironment(); // 'production'
$ab->isWithContext(
    experiment: 'checkout-button',
    variant: 'green',
    subjectId: (string) $userId,
    context: $context,
); // bool
```

### Yii3 integration

Package provides `config/params.php` and `config/di.php` via config-plugin.
Override in your application:

```php
// config/params.php
return [
    'rasuvaeff/yii3-ab-testing' => [
        'experiments' => [
            'checkout-button' => [
                'enabled' => true,
                'salt' => 'checkout-v1',
                'fallbackVariant' => 'control',
                'variants' => ['control' => 50, 'green' => 50],
            ],
        ],
    ],
];
```

The core wires only the `AbTesting` facade and the default
`WeightedHashAssignmentStrategy`. It does **not** bind `ExperimentProvider` (the
experiment source) nor `ExposureTracker` / `ConversionTracker` (the event sinks) —
those keys are owned by exactly one source each, so installing a storage/tracker
backend wires them with no `Duplicate key` conflict.

#### Experiment source (required)

`AbTesting` needs an `ExperimentProvider`. Without a storage backend, bind
`ConfigExperimentProvider` once in your app config (`config/common/di/*.php`),
reading the `experiments` params above:

```php
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ExperimentProvider;

/** @var array $params */

return [
    ExperimentProvider::class => [
        'class' => ConfigExperimentProvider::class,
        '__construct()' => [
            'config' => $params['rasuvaeff/yii3-ab-testing']['experiments'],
        ],
    ],
];
```

Installing `yii3-ab-testing-db` binds `ExperimentProvider` for you (database-backed,
runtime-editable) — drop the manual binding then. Bind it from a **single** source:
a backend plus a manual binding reintroduces the `yiisoft/config` `Duplicate key`
conflict.

### Delivering events

Two delivery paths are supported, and both produce the **same rows**:

| Path | How | Trade-off |
|---|---|---|
| **Durable** | `yii3-ab-testing-outbox` → exporter → ClickHouse | survives an analytics outage; needs a table and a worker |
| **Log shipping** | core's logger sinks → Vector / Fluent Bit / Kafka | no worker, no request-time network call; delivery is the collector's job |

Do **not** write to analytics storage from the request path. Under PHP-FPM a
per-request insert means many tiny writes and a network call inside the user's
latency — that is why the direct ClickHouse writer was removed in 2.0.

`CanonicalEventSerializer` produces the wire format both paths share, and the
logger sinks emit exactly its output under an `event` key:

```json
{"level":"info","message":"A/B test exposure","event":{"v":2,"event_id":"…","occurred_at":"2026-08-01 10:00:00.123","experiment":"checkout_button","variant":"b","subject_id":"…","decision_reason":"assigned","assignment_source":"computed","experiment_revision":"db:7","environment":"production","dimensions":"{\"country\":\"RU\"}"}}
```

Every value is scalar, and `dimensions` is a JSON **string** rather than a nested
object — the outbox exporter rejects nested payload fields, and the two paths must
stay byte-identical. A runnable collector config is in `examples/vector.toml`.

### Tracking backends (optional)

To persist exposures/conversions, opt in by binding the tracker interface to a
real implementation — either from a dedicated adapter package or once in your own
app config (`config/common/di/*.php`):

```php
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;

return [
    ExposureTracker::class => MyExposureTracker::class,
    ConversionTracker::class => MyConversionTracker::class,
];
```

Two ready-made sinks ship in core: `LoggerExposureTracker` /
`LoggerConversionTracker` write each event as one structured PSR-3 log record
(zero infrastructure, log level configurable). Like every tracker they are not
bound by core `config/di.php` (one-source rule) — bind them in your app config:

```php
use Psr\Log\LoggerInterface;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;

return [
    ExposureTracker::class => static fn (LoggerInterface $logger): ExposureTracker
        => new LoggerExposureTracker($logger),
];
```

Bind each interface from a **single** source. Installing two adapters that both
bind `ExposureTracker` (or a backend plus a manual binding) reintroduces a
`yiisoft/config` `Duplicate key` conflict — pick one, or compose them with the
built-in `CompositeExposureTracker` / `CompositeConversionTracker`, bound once in
your own app config:

```php
use Rasuvaeff\Yii3AbTesting\CompositeExposureTracker;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;

return [
    ExposureTracker::class => static fn (): ExposureTracker => new CompositeExposureTracker(
        new OutboxExposureTracker(/* ... */),
        new LoggerExposureTracker(/* ... */),
    ),
];
```

Trackers that buffer events implement
`FlushableTracker`; call `flush()` once at request end. The composite trackers
implement it too and propagate the flush to every flushable inner tracker, so the
application can flush through the bound tracker interface:

```php
use Rasuvaeff\Yii3AbTesting\FlushableTracker;

if ($tracker instanceof FlushableTracker) {
    $tracker->flush();
}
```

To emit at most one exposure for the same experiment, subject and configuration
within a request, wrap the real sink with `DeduplicatingExposureTracker`. The
wrapper has mutable request state: bind it request-scoped, or call `reset()` at
the request boundary in a long-running worker. It forwards `flush()` to a
flushable inner sink. `assign()` remains side-effect free.

```php
use Rasuvaeff\Yii3AbTesting\DeduplicatingExposureTracker;

$tracker = new DeduplicatingExposureTracker(tracker: $realExposureTracker);
$tracker->trackExposure($assignment);
$tracker->trackExposure($assignment); // suppressed in this request
```

### Targeting (optional)

Restrict an experiment to a subset of subjects by attaching a `TargetingRule`.
Subjects that don't match receive the fallback variant with `isFallback === true`
and `isTargetingMismatch()` true. `forcedVariant` bypasses targeting.

```php
use Rasuvaeff\Yii3AbTesting\AndTargetingRule;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Rasuvaeff\Yii3AbTesting\OrTargetingRule;

$experiment = new Experiment(
    name: 'checkout',
    enabled: true,
    salt: 'checkout-v1',
    fallbackVariant: 'control',
    variants: ['control' => 50, 'green' => 50],
    targeting: new AndTargetingRule(rules: [
        new EnvironmentTargetingRule(environments: ['production']),
        new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
    ]),
);

$assignment = $abTesting->assign(
    experiment: 'checkout',
    subjectId: $userId,
    context: new AssignmentContext(environment: 'production', attributes: ['plan' => 'pro']),
);

if ($assignment->isTargetingMismatch()) {
    // subject not in target segment — received fallback
}
```

Available built-in rules:

| Class | Matches when |
|---|---|
| `EnvironmentTargetingRule` | `context->getEnvironment()` is in the given list |
| `AttributeTargetingRule` | `context->getAttribute($name) === $value` (strict) |
| `AndTargetingRule` | all nested rules match (short-circuit) |
| `OrTargetingRule` | at least one nested rule matches (short-circuit) |

`ConfigExperimentProvider` accepts the same tagged arrays used by the DB JSON
representation (`environment`, `attribute`, `and`, `or`).

```php
'targeting' => [
    'type' => 'and',
    'rules' => [
        ['type' => 'environment', 'values' => ['production']],
        ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
    ],
],
```

`TargetingRuleCodecRegistry::decode()` and `encode()` provide the shared
config/DB representation. Register a custom `TargetingRuleCodec` in its
constructor to add another tagged rule type; custom codecs are checked before
the built-in codec.

### Sticky variants (optional)

Deterministic assignment keeps a subject in the same variant only while weights
are stable; changing weights or the variant set shifts bucket boundaries and
reshuffles subjects. To pin a subject to a variant across such changes, persist
the assignment through an `AssignmentStore`:

```php
interface AssignmentStore {
    public function get(string $experiment, string $subjectId): ?string;
    public function put(string $experiment, string $subjectId, string $variant): void;
}
```

`AbTesting::assign()` stays pure — sticky resolution is a separate layer.
Cookie/session implementations and a `SubjectIdMiddleware` for stable anonymous
identity ship in `yii3-ab-testing-web`. An assignment served from a store carries
`isSticky = true` so trackers can tell it apart from a fresh deterministic one.
Both `AbTesting` and the web package's sticky resolver implement
`AssignmentResolver`, whose `resolve()` signature mirrors `assign()`.

### Worker runtimes (RoadRunner, Swoole)

The experiment set is memoized per `ExperimentRegistry` instance. In a
long-running worker the `AbTesting` service survives across requests, so the
core's `config/di.php` registers a `reset` hook for `yiisoft/di`'s
`StateResetter`: runtimes that reset container state between requests re-read the
`ExperimentProvider` on the next request, and a kill switch flipped in the source
takes effect without a worker restart. In classic PHP-FPM nothing changes — the
service is rebuilt per request anyway.

## Assignment algorithm

This is bucketing algorithm **v1**. Its hash input, digest slice, variant sorting
and boundary rules are a compatibility contract and will not change in a patch
or minor release. Any future incompatible algorithm requires an explicit version
and a major release.

```
digest = sha256(salt + ':' + subjectId)   // 64-char hex
hash   = hexdec(digest[0:8])             // 32-bit unsigned
bucket = hash % totalWeight
```

Variants sorted by key. Cumulative weight boundaries determine assignment.

### Guarantees

- Same `salt` + `subjectId` → same variant, forever.
- Changing `salt` = full re-assignment (intentional reset).
- Changing weights/variants shifts bucket boundaries (partial re-assignment).
- To freeze a cohort, create new experiment with new `salt`.

## Security

- Experiment/variant names validated: `/^[a-z][a-z0-9_-]*$/`.
- Forced variant must pass allow-list. Unknown variant throws exception.
- No PII stored. Trackers are developer-controlled.
- `assign()`/`is()` are pure — no side effects.

## Examples

See [examples/](examples/) for complete usage scenarios.

## Development

```bash
make install       # composer install
make build         # full gate (validate + cs + psalm + test)
make cs-fix        # fix code style
make psalm         # static analysis
make test          # run testo
make test-coverage  # run coverage
make mutation       # mutation testing
make release-check  # build + rector + bc-check + mutation
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
