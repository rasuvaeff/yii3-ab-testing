---
layout: home
title: Yii3 A/B Testing
description: Deterministic A/B testing for Yii3 — stateless hash assignment, targeting, sticky variants, one event contract for durable and log-shipped delivery, and ClickHouse analytics.
hero:
  name: Yii3 A/B Testing
  text: Experiments you can reproduce, rows you can trust.
  tagline: Five packages around one contract — deterministic assignment, event schema v2, and two delivery paths that write identical rows.
  image:
    src: /logo-mark.svg
    alt: Yii3 A/B Testing logo
  actions:
    - theme: brand
      text: Getting started
      link: /guide/getting-started
    - theme: alt
      text: Choose a combination
      link: /integration
    - theme: alt
      text: View on GitHub
      link: https://github.com/rasuvaeff/yii3-ab-testing
features:
  - title: Stateless, deterministic assignment
    details: sha256(salt:subjectId) picks the bucket. Same salt and subject, same variant — on every server, with no storage. The algorithm is a versioned public contract.
    link: /guide/assignment
  - title: Two independent answers per decision
    details: Every assignment says why (assigned, forced, disabled, targeting miss) and where it came from (computed or a sticky store). Only "assigned" counts in reports.
    link: /guide/decision
  - title: One event contract, two delivery paths
    details: The durable outbox and plain log shipping serialize through the same CanonicalEventSerializer and land as the same rows in the same ClickHouse tables.
    link: /guide/delivery
  - title: Sticky across reweights — correctly
    details: Configuration-aware stores pin a subject to a variant for one experiment definition and drop the pin the moment that definition changes.
    link: /guide/persistence
  - title: Attribution rules fixed in the core
    details: The attribution window and the repeated-conversion policy live next to the events, so every report answers the same question the same way.
    link: /guide/attribution
  - title: Pick only what you run
    details: Config or database definitions, cookie or database stickiness, outbox or logs. One package per axis, and exactly one source per DI key.
    link: /integration
---

## Three calls

::: code-group

```php [Assign]
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

$assignment = $ab->assign(experiment: 'checkout-button', subjectId: $userId);

if ($assignment->isVariant('green')) {
    // render the green button
}
```

```php [Track]
// assign() never tracks. Record the exposure when the variant is shown...
$exposure = $ab->trackExposure($assignment);

// ...and the conversion when the goal happens, linked to that exposure.
$ab->trackConversion($assignment, goal: 'purchase', exposure: $exposure);

// Converting in a later request? Carry the receipt instead of re-assigning.
$_SESSION['ab_receipt'] = $exposure->receipt()->toArray();
```

```sql [Read results]
SELECT variant, uniqExact(subject_id) AS subjects
FROM ab_exposures_v2 FINAL
WHERE experiment = 'checkout-button'
  AND decision_reason = 'assigned'
GROUP BY variant
```

:::

## The family

| Package | Layer | What it adds |
|---|---|---|
| [yii3-ab-testing](/packages/core) | Assignment, tracking | The facade, hash bucketing v1, targeting, events v2, attribution rules, PSR-3 sinks |
| [yii3-ab-testing-db](/packages/db) | Definitions, persistence | Runtime-editable experiments with revisions, a console control plane, `DbAssignmentStore` |
| [yii3-ab-testing-web](/packages/web) | Identity, persistence | Anonymous subject id cookie, consent, signed-cookie sticky store, signed receipts |
| [yii3-ab-testing-outbox](/packages/outbox) | Delivery | Durable exposure and conversion trackers on the transactional outbox |
| [yii3-ab-testing-clickhouse](/packages/clickhouse) | Analytics | The schema v2 tables both delivery paths write into, plus their migrations |
