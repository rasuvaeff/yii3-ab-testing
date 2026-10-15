---
title: Targeting
description: Restrict an experiment to a segment with environment and attribute rules combined by and/or, shared between config and the database through TargetingRuleCodecRegistry.
---

# Targeting

Targeting decides **who is eligible**, never **which variant**: a subject
outside the segment gets `fallbackVariant` with reason
`fallback_targeting_mismatch`, and a subject inside it is bucketed exactly as if
there were no rule. The context never enters the hash.

## Context

Rules read an `AssignmentContext` passed to `assign()`:

```php
use Rasuvaeff\Yii3AbTesting\AssignmentContext;

$context = AssignmentContext::forEnvironment('production')
    ->withAttribute('plan', 'pro')
    ->withAttribute('country', 'DE');

$assignment = $ab->assign(
    experiment: 'checkout',
    subjectId: $userId,
    context: $context,
);

$ab->isWithContext(experiment: 'checkout', variant: 'green', subjectId: $userId, context: $context);
```

The context also travels into the `Assignment`, and from there into events —
but only the attributes an `AnalyticsContextPolicy` allows reach storage; see
[Events v2](/guide/events#analytics-dimensions).

## Built-in rules

| Rule | Tag | Matches when |
|---|---|---|
| `EnvironmentTargetingRule` | `environment` | the context environment is in the list |
| `AttributeTargetingRule` | `attribute` | `getAttribute($name) === $value` (strict) |
| `AndTargetingRule` | `and` | every nested rule matches (short-circuit) |
| `OrTargetingRule` | `or` | at least one nested rule matches (short-circuit) |

```php
use Rasuvaeff\Yii3AbTesting\AndTargetingRule;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;

new AndTargetingRule(rules: [
    new EnvironmentTargetingRule(environments: ['production']),
    new AttributeTargetingRule(attribute: 'plan', value: 'pro'),
]);
```

## One representation for config and the database

`ConfigExperimentProvider` and `DbExperimentProvider` read the same tagged
arrays, so a rule written in params can move to the `targeting` JSON column of
the database package unchanged:

```php
'targeting' => [
    'type' => 'and',
    'rules' => [
        ['type' => 'environment', 'values' => ['production']],
        ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
    ],
],
```

`TargetingRuleCodecRegistry` owns that format through `decode()` and
`encode()`. To add a rule type, implement `TargetingRuleCodec` and pass it to
the registry's constructor; custom codecs are consulted before the built-in one.
Decoding rejects trees nested deeper than 64 levels.

## Precedence

| Situation | Result |
|---|---|
| `forcedVariant` given | the forced variant — targeting is not evaluated |
| Experiment disabled | `fallbackVariant`, reason `fallback_disabled` |
| Rule does not match | `fallbackVariant`, reason `fallback_targeting_mismatch` |
| Rule matches, or no rule | hash bucketing, reason `assigned` |

Sticky stores follow the same order: forced, disabled and targeting decisions
return before the store is read, so a stored pin never overrides the kill switch.
