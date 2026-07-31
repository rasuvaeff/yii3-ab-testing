# rasuvaeff/yii3-ab-testing

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-ab-testing/php)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-ab-testing.svg)](https://github.com/rasuvaeff/yii3-ab-testing/blob/master/LICENSE.md)
[English version](README.md)

Детерминированное A/B-тестирование для приложений на Yii3. Stateless-назначение
вариантов, взвешенные варианты, форсированный вариант для QA, явное отслеживание
экспоузов и конверсий.

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> который можно передать модели как контекст.
> Проекты с Composer-плагином [llm/skills](https://github.com/roxblnfk/skills)
> дополнительно получают agent-скилл этого пакета в `.agents/skills/`
> автоматически при установке.

## Требования

- PHP 8.3+ (64-бит — hash-бакет превышает `PHP_INT_MAX` на 32-битных сборках)

## Установка

```bash
composer require rasuvaeff/yii3-ab-testing
```

## Использование

### Конфигурация экспериментов

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

Определения экспериментов берутся из `ExperimentProvider`. `ConfigExperimentProvider`
читает статический массив; storage-backend (например, `yii3-ab-testing-db`) даёт
провайдера поверх БД, чтобы экспериментами можно было управлять во время выполнения
без деплоя.
Config-определения получают детерминированный hash `configurationId`. Runtime-
провайдеры могут передать в `Experiment` явный строковый ID, например revision из
БД; каждый `Assignment` переносит его для аналитики и дедупликации экспоузов.

### Назначение варианта

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

Назначение неопределённого эксперимента бросает `Exception\InvalidExperimentException`;
форсирование варианта, которого нет в эксперименте, бросает
`Exception\InvalidVariantException`. Загруженный набор экспериментов доступен через
`$ab->getRegistry()` — `ExperimentRegistry` с методами `get()`, `has()`, `all()` и
`reset()`. Реестр ленивый: `ExperimentProvider` опрашивается при первом доступе,
далее результат мемоизируется.

### Форсированный вариант (QA)

```php
$assignment = $ab->assign(
    experiment: 'checkout-button',
    subjectId: (string) $userId,
    forcedVariant: 'green',
);
```

### Отслеживание экспоузов и конверсий

```php
// assign() does NOT auto-track. Call explicitly:
$ab->trackExposure($assignment);

// On conversion event:
$ab->trackConversion($assignment, goal: 'purchase');
```

Цель конверсии должна содержать хотя бы один непробельный символ. Некорректная
цель отклоняется до вызова любого трекера.

### Контекст назначения (необязательно)

Передайте `AssignmentContext`, чтобы атрибутировать метрики по среде/сегменту. Он
попадает в возвращаемый `Assignment`, чтобы трекеры могли его прочитать. Контекст
может влиять на допуск через targeting, но никогда не меняет детерминированный
hash-бакет.

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

### Интеграция с Yii3

Пакет предоставляет `config/params.php` и `config/di.php` через config-plugin.
Переопределяйте в приложении:

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

Ядро биндит только фасад `AbTesting` и стратегию по умолчанию
`WeightedHashAssignmentStrategy`. Оно **не** биндит ни `ExperimentProvider`
(источник экспериментов), ни `ExposureTracker` / `ConversionTracker` (приёмники
событий) — каждый из этих ключей принадлежит ровно одному источнику, поэтому
установка storage/tracker backend-а подключает их без конфликта `Duplicate key`.

#### Источник экспериментов (обязательно)

`AbTesting` нужен `ExperimentProvider`. Без storage-backend-а привяжите
`ConfigExperimentProvider` один раз в конфиге приложения (`config/common/di/*.php`),
читая параметры `experiments` выше:

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

Установка `yii3-ab-testing-db` биндит `ExperimentProvider` за вас (поверх БД,
редактируемо во время выполнения) — тогда уберите ручной биндинг. Биндите из
**единственного** источника: backend плюс ручной биндинг вновь приведут к конфликту
`yiisoft/config` `Duplicate key`.

### Backend-ы отслеживания (необязательно)

Чтобы сохранять экспоузы/конверсии, подключите их, забиндив интерфейс трекера на
реальную реализацию — либо из специализированного пакета-адаптера, либо один раз в
конфиге своего приложения (`config/common/di/*.php`):

```php
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;

return [
    ExposureTracker::class => MyExposureTracker::class,
    ConversionTracker::class => MyConversionTracker::class,
];
```

Два готовых приёмника идут в ядре: `LoggerExposureTracker` /
`LoggerConversionTracker` пишут каждое событие как одну структурированную PSR-3
лог-запись (нулевая инфраструктура, уровень лога настраивается). Как и все трекеры,
они не биндятся ядром в `config/di.php` (правило одного источника) — биндите их в
конфиге приложения:

```php
use Psr\Log\LoggerInterface;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;

return [
    ExposureTracker::class => static fn (LoggerInterface $logger): ExposureTracker
        => new LoggerExposureTracker($logger),
];
```

Биндите каждый интерфейс из **единственного** источника. Установка двух адаптеров,
оба из которых биндят `ExposureTracker` (или backend плюс ручной биндинг), вновь
приводит к конфликту `yiisoft/config` `Duplicate key` — выберите один или
композируйте их через встроенные `CompositeExposureTracker` /
`CompositeConversionTracker`, забиндив один раз в конфиге приложения:

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

Трекеры, буферизующие события (например, ClickHouse-адаптер), реализуют
`FlushableTracker`; вызывайте `flush()` один раз в конце запроса. Композитные
трекеры тоже реализуют его и пробрасывают flush каждому flushable внутреннему
трекеру, поэтому приложение может делать flush через привязанный интерфейс трекера:

```php
use Rasuvaeff\Yii3AbTesting\FlushableTracker;

if ($tracker instanceof FlushableTracker) {
    $tracker->flush();
}
```

Чтобы в рамках запроса отправлять не больше одного экспоуза для одной комбинации
experiment, subject и configuration, оберните настоящий sink в
`DeduplicatingExposureTracker`. Обёртка хранит mutable request-state: биндите её
request-scoped либо вызывайте `reset()` на границе запроса в долго живущем воркере.
Она пробрасывает `flush()` во внутренний flushable sink. `assign()` остаётся без
побочных эффектов.

```php
use Rasuvaeff\Yii3AbTesting\DeduplicatingExposureTracker;

$tracker = new DeduplicatingExposureTracker(tracker: $realExposureTracker);
$tracker->trackExposure($assignment);
$tracker->trackExposure($assignment); // подавлен в этом запросе
```

### Таргетинг (необязательно)

Ограничьте эксперимент подмножеством субъектов, прикрепив `TargetingRule`. Субъекты,
которые не прошли проверку, получают fallback-вариант с `isFallback === true` и
`isTargetingMismatch === true`. `forcedVariant` обходит таргетинг.

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

if ($assignment->isTargetingMismatch) {
    // subject not in target segment — received fallback
}
```

Доступные встроенные правила:

| Класс | Совпадает, когда |
|---|---|
| `EnvironmentTargetingRule` | `context->getEnvironment()` есть в заданном списке |
| `AttributeTargetingRule` | `context->getAttribute($name) === $value` (строгое сравнение) |
| `AndTargetingRule` | все вложенные правила совпадают (short-circuit) |
| `OrTargetingRule` | хотя бы одно вложенное правило совпадает (short-circuit) |

`ConfigExperimentProvider` принимает те же tagged-массивы, что используются в
JSON-представлении БД (`environment`, `attribute`, `and`, `or`).

```php
'targeting' => [
    'type' => 'and',
    'rules' => [
        ['type' => 'environment', 'values' => ['production']],
        ['type' => 'attribute', 'attribute' => 'plan', 'value' => 'pro'],
    ],
],
```

`TargetingRuleCodecRegistry::decode()` и `encode()` задают общее представление
для config/DB. Передайте собственный `TargetingRuleCodec` в конструктор registry,
чтобы добавить новый tagged-тип; custom codecs проверяются до встроенного.

### Sticky-варианты (необязательно)

Детерминированное назначение удерживает субъекта в одном варианте, только пока веса
стабильны; изменение весов или набора вариантов сдвигает границы бакетов и
перетасовывает субъектов. Чтобы закрепить субъекта за вариантом при таких
изменениях, сохраняйте назначение через `AssignmentStore`:

```php
interface AssignmentStore {
    public function get(string $experiment, string $subjectId): ?string;
    public function put(string $experiment, string $subjectId, string $variant): void;
}
```

`AbTesting::assign()` остаётся чистым — sticky-разрешение — отдельный слой.
Cookie/session-реализации и `SubjectIdMiddleware` для стабильной анонимной
идентификации живут в `yii3-ab-testing-web`. Назначение, отданное из хранилища,
несёт `isSticky = true`, чтобы трекеры могли отличить его от свежего
детерминированного.
И `AbTesting`, и sticky resolver из web-пакета реализуют `AssignmentResolver`,
чья сигнатура `resolve()` совпадает с `assign()`.

### Worker-рантаймы (RoadRunner, Swoole)

Набор экспериментов мемоизируется per-instance `ExperimentRegistry`. В долго живущем
воркере сервис `AbTesting` переживает несколько запросов, поэтому ядро в
`config/di.php` регистрирует `reset`-хук для `StateResetter` из `yiisoft/di`:
рантаймы, сбрасывающие состояние контейнера между запросами, перечитывают
`ExperimentProvider` при следующем запросе, и kill-switch, переключённый в источнике,
применяется без перезапуска воркера. В классическом PHP-FPM ничего не меняется —
сервис и так пересоздаётся на каждый запрос.

## Алгоритм назначения

Это алгоритм бакетинга **v1**. Его вход хеша, срез digest, сортировка вариантов и
правила границ являются контрактом совместимости и не изменятся в patch- или
minor-релизе. Любой будущий несовместимый алгоритм потребует явной версии и
major-релиза.

```
digest = sha256(salt + ':' + subjectId)   // 64-char hex
hash   = hexdec(digest[0:8])             // 32-bit unsigned
bucket = hash % totalWeight
```

Варианты сортируются по ключу. Границы кумулятивных весов определяют назначение.

### Гарантии

- Одинаковые `salt` + `subjectId` → один и тот же вариант, всегда.
- Изменение `salt` = полное переназначение (преднамеренный сброс).
- Изменение весов/вариантов сдвигает границы бакетов (частичное переназначение).
- Чтобы зафиксировать когорту, создайте новый эксперимент с новым `salt`.

## Безопасность

- Имена экспериментов/вариантов валидируются: `/^[a-z][a-z0-9_-]*$/`.
- Форсированный вариант должен пройти allow-list. Неизвестный вариант бросает исключение.
- PII не сохраняются. Трекеры контролируются разработчиком.
- `assign()`/`is()` чистые — без побочных эффектов.

## Примеры

Полные сценарии использования — в [examples/](examples/).

## Разработка

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

`make test-coverage` и `make mutation` поднимают `pcov` внутри контейнера
`composer:2`, потому что в базовом образе нет драйвера покрытия.

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
