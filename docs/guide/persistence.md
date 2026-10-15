---
title: Sticky assignments
description: When a deterministic assignment is not enough, AssignmentStore and ConfigurationAwareAssignmentStore pin subjects to variants — and drop the pin when the experiment definition changes.
---

# Sticky assignments

Deterministic assignment keeps a subject in one variant **while the weights
hold**. Reweight an experiment and bucket boundaries move; the subjects between
the old and the new boundary switch variant. A store pins them instead.

`AbTesting::assign()` stays pure — stickiness is a separate layer,
`StickyAssignmentResolver` from `yii3-ab-testing-web`, wrapped around the
facade. Both implement `AssignmentResolver`, whose `resolve()` mirrors
`assign()`, so calling code does not change.

## Two store contracts

Both interfaces live in the core, because two sibling adapters must not depend
on each other to share a contract.

```php
interface AssignmentStore
{
    public function get(string $experiment, string $subjectId): ?string;

    public function put(string $experiment, string $subjectId, string $variant): void;
}

interface ConfigurationAwareAssignmentStore extends AssignmentStore
{
    public function getForConfiguration(string $experiment, string $subjectId, ?string $configurationId): ?string;

    public function putForConfiguration(string $experiment, string $subjectId, string $variant, ?string $configurationId): void;
}
```

A plain `AssignmentStore` pins a subject **forever**. That is right while the
experiment is stable and wrong the moment it is reweighted: the subject keeps a
variant drawn from boundaries that no longer exist, and the new split never
reaches the people already pinned.

A `ConfigurationAwareAssignmentStore` keys the pin by the experiment's
`configurationId`. `getForConfiguration()` returns `null` when nothing is stored
**for that configuration** — including when a variant is stored for a different
one — so a reweight drops stale pins instead of replaying them.

| Provider | `configurationId` |
|---|---|
| `ConfigExperimentProvider` | a canonical SHA-256 of the definition |
| `DbExperimentProvider` | `db:<revision>`, incremented on every write |

## Implementations

| Store | Package | Survives |
|---|---|---|
| `CookieAssignmentStore` (signed cookie) | `yii3-ab-testing-web` | reweights; **not** a cleared cookie or a new device |
| `DbAssignmentStore` | `yii3-ab-testing-db` | reweights, devices, cleared cookies |
| your own | implement `ConfigurationAwareAssignmentStore` | — |

Both shipped stores are configuration-aware.

### Signed cookie

`StickyAssignmentMiddleware` builds a request-scoped `CookieAssignmentStore`,
exposes the resolver through `StickyAssignmentRequestAccessor`, and writes
changed assignments back to the response. The cookie is signed, capped by
entry count and by the real `Set-Cookie` size, and evicts oldest-first. See the
[web package](/packages/web) for the full setup, including consent.

### Database

```php
use Rasuvaeff\Yii3AbTestingDb\DbAssignmentStore;
use Rasuvaeff\Yii3AbTestingWeb\StickyAssignmentResolver;

$resolver = new StickyAssignmentResolver(
    resolver: $ab,
    store: new DbAssignmentStore(db: $connection),
);

$assignment = $resolver->resolve(experiment: 'checkout-button', subjectId: $userId);
```

`DbAssignmentStore` is keyed by subject rather than by browser, so one
authenticated user keeps one variant across devices. It also carries the
erasure operations: `forget($subjectId)` removes a subject from every
experiment, `deleteExperiment($name)` drops all pins of one experiment.

## What a store never overrides

The resolver asks the store last. Forced variants, a disabled experiment and a
targeting miss return before the store is read, and fallback results are never
written — so a pin cannot bypass the kill switch or a targeting rule. A stored
variant the experiment no longer defines is ignored.

An assignment served from a store has `source = store` (`isSticky()` is
`true`), which keeps it distinguishable in the event rows; see
[Decision reason and source](/guide/decision).
