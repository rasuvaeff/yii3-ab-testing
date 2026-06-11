# rasuvaeff/yii3-ab-testing

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-ab-testing/php)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-ab-testing.svg)](https://github.com/rasuvaeff/yii3-ab-testing/blob/master/LICENSE.md)

Deterministic A/B testing for Yii3 applications. Stateless assignment, weighted
variants, forced variant for QA, explicit exposure/conversion tracking.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference
> you can pass as context.

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

### Track exposure and conversion

```php
// assign() does NOT auto-track. Call explicitly:
$ab->trackExposure($assignment);

// On conversion event:
$ab->trackConversion($assignment, goal: 'purchase');
```

### Assignment context (optional)

Pass an `AssignmentContext` to attribute metrics by environment/segment. It is
carried into the returned `Assignment` (so trackers can read it) but does **not**
change which variant is selected — variant selection stays deterministic.

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
        new ClickHouseExposureTracker(/* ... */),
        new LoggerExposureTracker(/* ... */),
    ),
];
```

Trackers that buffer events (e.g. the ClickHouse adapter) implement
`FlushableTracker`; call `flush()` once at request end. The composite trackers
implement it too and propagate the flush to every flushable inner tracker, so the
application can flush through the bound tracker interface:

```php
use Rasuvaeff\Yii3AbTesting\FlushableTracker;

if ($tracker instanceof FlushableTracker) {
    $tracker->flush();
}
```

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

### Worker runtimes (RoadRunner, Swoole)

The experiment set is memoized per `ExperimentRegistry` instance. In a
long-running worker the `AbTesting` service survives across requests, so the
core's `config/di.php` registers a `reset` hook for `yiisoft/di`'s
`StateResetter`: runtimes that reset container state between requests re-read the
`ExperimentProvider` on the next request, and a kill switch flipped in the source
takes effect without a worker restart. In classic PHP-FPM nothing changes — the
service is rebuilt per request anyway.

## Assignment algorithm

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
make test          # run phpunit
make test-coverage  # run coverage
make mutation       # mutation testing
make release-check  # build + rector + bc-check + mutation
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
