# rasuvaeff/yii3-ab-тестирование
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-ab-testing.svg)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-ab-testing/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-ab-testing/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-ab-testing)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-ab-testing/php)](https://packagist.org/packages/rasuvaeff/yii3-ab-testing)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-ab-testing.svg)](https://github.com/rasuvaeff/yii3-ab-testing/blob/master/LICENSE.md)
Детерминированное A/B-тестирование приложений Yii3. Назначение без сохранения состояния, взвешенные варианты
, принудительный вариант для контроля качества, явное отслеживание воздействия/конверсий.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) имеет компактную ссылку на API
 > которую можно передать в качестве контекста. @@ЛИНИЯ@@
## Требования
- PHP 8.3+ (64-разрядная версия — хеш-корзина превышает PHP_INT_MAX в 32-разрядных сборках)

## Установка
```bash
composer require rasuvaeff/yii3-ab-testing
```
## Использование
### Настройка экспериментов
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
Определения экспериментов берутся из «ExperimentProvider». `ConfigExperimentProvider`
 считывает статический массив; серверная часть хранилища (например, `yii3-ab-testing-db`) предоставляет поставщика
, поддерживаемого базой данных, поэтому эксперименты можно переключать во время выполнения без развертывания. @@ЛИНИЯ@@
### Назначить вариант
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
Назначение эксперимента, который не определен, вызывает
 `Exception\InvalidExperimentException`; принудительный вариант, которого в эксперименте нет
, выдает `Exception\InvalidVariantException`. Загруженный набор экспериментов
 можно проверить через `$ab->getRegistry()` — `ExperimentRegistry` с `get()`,
 `has()`, `all()` и `reset()`. Реестр ленив: `ExperimentProvider` запрашивается
 при первом доступе и впоследствии запоминается. @@ЛИНИЯ@@
### Форсированный вариант (QA)
```php
$assignment = $ab->assign(
    experiment: 'checkout-button',
    subjectId: (string) $userId,
    forcedVariant: 'green',
);
```
### Отслеживайте показы и конверсии
```php
// assign() does NOT auto-track. Call explicitly:
$ab->trackExposure($assignment);

// On conversion event:
$ab->trackConversion($assignment, goal: 'purchase');
```
### Контекст назначения (необязательно)
Передайте AssignmentContext для атрибутирования показателей по среде/сегменту. Оно
 переносится в возвращаемое `Назначение` (чтобы трекеры могли его прочитать), но **не**
 не меняет выбранный вариант — выбор варианта остается детерминированным. @@ЛИНИЯ@@
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
### Интеграция с Yii3
Пакет предоставляет `config/params.php` и `config/di.php` через config-plugin.
 Переопределение в вашем приложении:

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
Ядро связывает только фасад AbTesting и стандартную
 WeightedHashAssignmentStrategy. Он **не** связывает ни `ExperimentProvider` (источник эксперимента
), ни `ExposureTracker`/`ConversionTracker` (приемники событий) —
 каждый из этих ключей принадлежит ровно одному источнику, поэтому установка бэкэнда хранилища/трекера
 связывает их без конфликта `Duplate key`. @@ЛИНИЯ@@
#### Источник эксперимента (обязательно)
«AbTesting» нуждается в «ExperimentProvider». Без серверной части хранилища привяжите
 `ConfigExperimentProvider` один раз в конфигурации вашего приложения (`config/common/di/*.php`),
, прочитав параметры `experiments` выше:

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
Установка `yii3-ab-testing-db` привязывает для вас `ExperimentProvider` (с поддержкой базы данных,
, редактируемый во время выполнения) — затем отмените привязку вручную. Свяжите его из **единственного** источника:
 бэкэнд плюс ручная привязка вновь приводит к конфликту `yiisoft/config` `Duplate key`
. @@ЛИНИЯ@@
### Серверы отслеживания (необязательно)
Чтобы сохранить показы/конверсии, зарегистрируйтесь, привязав интерфейс трекера к реальной реализации
 — либо из специального пакета адаптера, либо один раз в вашей собственной конфигурации приложения
 (`config/common/di/*.php`):

```php
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;

return [
    ExposureTracker::class => MyExposureTracker::class,
    ConversionTracker::class => MyConversionTracker::class,
];
```
Два готовых приемника поставляются в ядре: `LoggerExposureTracker` /
 `LoggerConversionTracker` записывает каждое событие как одну структурированную запись журнала PSR-3
 (нулевая инфраструктура, настраиваемый уровень журнала). Как и любой трекер, они не привязаны
 основным `config/di.php` (правилом одного источника) — привяжите их в конфигурации вашего приложения:

```php
use Psr\Log\LoggerInterface;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\LoggerExposureTracker;

return [
    ExposureTracker::class => static fn (LoggerInterface $logger): ExposureTracker
        => new LoggerExposureTracker($logger),
];
```
Свяжите каждый интерфейс из **одного** источника. Установка двух адаптеров, которые
 связывают `ExposureTracker` (или серверную часть плюс привязку вручную), повторно приводит к конфликту
 `yiisoft/config` `Duplate key` — выберите один или скомпонуйте их с помощью
 встроенного `CompositeExposureTracker` / `CompositeConversionTracker`, привязанного один раз в
 вашей собственной конфигурации приложения:

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
Трекеры, которые буферизуют события (например, адаптер ClickHouse), реализуют
 `FlushableTracker`; вызовите `flush()` один раз в конце запроса. Составные трекеры
 тоже реализуют это и передают сброс на каждый сбрасываемый внутренний трекер, поэтому приложение
 может выполнить сброс через интерфейс привязанного трекера:

```php
use Rasuvaeff\Yii3AbTesting\FlushableTracker;

if ($tracker instanceof FlushableTracker) {
    $tracker->flush();
}
```
### Таргетинг (необязательно)
Ограничьте эксперимент подмножеством субъектов, прикрепив правило TargetingRule.
 Субъекты, которые не совпадают, получают запасной вариант с `isFallback === true`
 и `isTargetingMismatch === true`. `forcedVariant` обходит таргетинг. @@ЛИНИЯ@@
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

 | Класс | Соответствует, когда |
 |---|---|
 | `EnvironmentTargetingRule` | `context->getEnvironment()` находится в данном списке |
 | `AttributeTargetingRule` | `context->getAttribute($name) === $value` (строгое) |
 | `AndTargetingRule` | все вложенные правила совпадают (короткое замыкание) |
 | `OrTargetingRule` | хотя бы одно вложенное правило соответствует (короткое замыкание) | @@ЛИНИЯ@@
### Прикрепленные варианты (необязательно)
Детерминированное присвоение сохраняет субъект в одном и том же варианте только до тех пор, пока веса
 стабильны; изменение весов или набора вариантов смещает границы корзины, а
 перетасовывает темы. Чтобы закрепить тему за вариантом среди таких изменений, сохраните
 назначение через `AssignmentStore`:

```php
interface AssignmentStore {
    public function get(string $experiment, string $subjectId): ?string;
    public function put(string $experiment, string $subjectId, string $variant): void;
}
```
`AbTesting::assign()` остается чистым — фиксированное разрешение — это отдельный уровень.
 Реализация файлов cookie/сессий и `SubjectIdMiddleware` для стабильной анонимной идентификации
 поставляется в `yii3-ab-testing-web`. Задание, полученное из хранилища, содержит
 `isSticky = true`, поэтому трекеры могут отличить его от нового детерминированного задания. @@ЛИНИЯ@@
### Рабочие среды выполнения (RoadRunner, Swoole)
Набор экспериментов запоминается для каждого экземпляра ExperimentRegistry. В
 долго работающем рабочем процессе служба `AbTesting` сохраняется между запросами, поэтому `config/di.php` ядра
 регистрирует перехват `reset` для
 `StateResetter` `yiisoft/di`: среды выполнения, которые сбрасывают состояние контейнера между запросами, перечитывают
 `ExperimentProvider` при следующем запросе, и в исходном коде включается переключатель уничтожения.
 вступает в силу без перезапуска рабочего процесса. В классическом PHP-FPM ничего не меняется — сервис
 все равно пересобирается для каждого запроса. @@ЛИНИЯ@@
## Алгоритм назначения
```
digest = sha256(salt + ':' + subjectId)   // 64-char hex
hash   = hexdec(digest[0:8])             // 32-bit unsigned
bucket = hash % totalWeight
```
Варианты отсортированы по ключу. Границы совокупного веса определяют назначение. @@ЛИНИЯ@@
### Гарантии
— Та же соль + subjectId → тот же вариант, навсегда.
 - Изменение `salt` = полное переназначение (преднамеренный сброс).
 - Изменение весов/вариантов смещает границы сегмента (частичное переназначение).
 - Чтобы заморозить когорту, создайте новый эксперимент с новой «солью». @@ЛИНИЯ@@
## Безопасность
- Проверены имена экспериментов/вариантов: `/^[a-z][a-z0-9_-]*$/`.
 - Принудительный вариант должен пройти разрешенный список. Неизвестный вариант выдает исключение.
 - Личная информация не сохранена. Трекеры контролируются разработчиками.
 - `assign()`/`is()` являются чистыми — никаких побочных эффектов. @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) для получения полных сценариев использования. @@ЛИНИЯ@@
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
`make test-coverage` и `makemutation` загружают `pcov` внутри контейнера
 `composer:2`, поскольку базовый образ не имеет драйвера покрытия. @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
