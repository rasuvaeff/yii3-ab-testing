---
title: Getting started
description: Install the core package, define one experiment, assign a variant and record exposure and conversion — then decide which family packages you need.
---

# Getting started

The core package alone runs a complete experiment: definitions from config,
assignment, and events written to your PSR-3 logger. Every other package in the
family replaces one of those pieces.

## Requirements

- PHP 8.3 – 8.5, **64-bit**: the hash bucket is a 32-bit unsigned value, which
  exceeds `PHP_INT_MAX` on a 32-bit build.

## Install

```bash
composer require rasuvaeff/yii3-ab-testing
```

## Define an experiment

```php
use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

$ab = new AbTesting(
    provider: new ConfigExperimentProvider(config: [
        'checkout-button' => [
            'enabled' => true,
            'salt' => 'checkout-v1',
            'fallbackVariant' => 'control',
            'variants' => ['control' => 50, 'green' => 50],
        ],
    ]),
    strategy: new WeightedHashAssignmentStrategy(),
);
```

| Key | Meaning |
|---|---|
| `enabled` | The kill switch. A disabled experiment returns `fallbackVariant` to everyone |
| `salt` | Part of the hash input. Changing it re-assigns every subject |
| `fallbackVariant` | What a subject gets when the experiment is off or targeting does not match. Must be one of `variants` |
| `variants` | Variant name to non-negative integer weight. Weights are relative, not percentages |
| `targeting` | Optional rule tree — see [Targeting](/guide/targeting) |

Names of experiments and variants match `/^[a-z][a-z0-9_-]*\z/`.

## Assign, show, record

```php
$assignment = $ab->assign(experiment: 'checkout-button', subjectId: (string) $userId);

if ($assignment->isVariant('green')) {
    // render the green button
}

$exposure = $ab->trackExposure($assignment);

// later in the same request, when the goal happens
$ab->trackConversion($assignment, goal: 'purchase', exposure: $exposure);
```

`assign()` and `is()` are pure: they never call a tracker. Exposure is recorded
only where you call `trackExposure()` — at the point the variant is actually
shown, not where it is computed.

Without a tracker bound, events go to the no-op `NullExposureTracker` and
`NullConversionTracker`. To see them, pass `LoggerExposureTracker` and
`LoggerConversionTracker` to the constructor; see [Delivery](/guide/delivery).

## In a Yii3 application

The core ships `config/di.php` and `config/params.php` through the config
plugin. It binds the `AbTesting` facade and the assignment strategy — and
deliberately **not** the experiment source or the event sinks. Without a
storage package, bind the source once in your application:

```php
// config/common/di/ab-testing.php
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

[DI: one source per key](/guide/di) explains why the core leaves those keys to
you, and what changes when a storage package is installed.

## What to add next

| You need | Add | Read |
|---|---|---|
| Toggle or reweight without a deploy | `yii3-ab-testing-db` | [Database package](/packages/db) |
| A stable id for anonymous visitors | `yii3-ab-testing-web` | [Web package](/packages/web) |
| Keep a variant across a reweight | `-web` (cookie) or `-db` (table) | [Sticky assignments](/guide/persistence) |
| Events that survive an analytics outage | `yii3-ab-testing-outbox` | [Two paths, one row](/guide/delivery) |
| Tables to report from | `yii3-ab-testing-clickhouse` | [Reading results](/guide/analytics) |

[Choosing a combination](/integration) walks all eight decisions in one place.
