<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\AssignmentStrategy;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;
use Rasuvaeff\Yii3AbTesting\ExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Di\StateResetter;

/**
 * `config/di.php` is covered by neither cs, psalm nor the unit suite — it is not
 * in `src`. Without this test a change there surfaces only at deploy time, so it
 * is exercised through a real container rather than by reading the array.
 */
#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    public function facadeResolvesOnceTheApplicationSuppliesAProvider(): void
    {
        $ab = $this->container()->get(AbTesting::class);

        $assignment = $ab->assign(experiment: 'checkout', subjectId: 'u1');
        $event = $ab->trackExposure($assignment);

        Assert::instanceOf($ab, AbTesting::class);
        // The new clock, id generator and context policy arrive through
        // constructor defaults, so no extra binding is required.
        Assert::same(\strlen($event->eventId), 36);
        Assert::same($event->dimensions, []);
    }

    public function strategyIsBoundToTheSingleImplementation(): void
    {
        Assert::instanceOf(
            $this->container()->get(AssignmentStrategy::class),
            WeightedHashAssignmentStrategy::class,
        );
    }

    /**
     * The one-source rule: core must never bind the swappable keys, or a backend
     * package binding them too makes `yiisoft/config` throw `Duplicate key`.
     */
    public function coreBindsNeitherProviderNorTracker(): void
    {
        $definitions = require __DIR__ . '/../config/di.php';

        Assert::false(\array_key_exists(ExperimentProvider::class, $definitions));
        Assert::false(\array_key_exists(ExposureTracker::class, $definitions));
        Assert::false(\array_key_exists(ConversionTracker::class, $definitions));
    }

    /**
     * Worker runtimes reuse the container across requests; without the reset
     * hook the enabled kill switch would be frozen for the worker's lifetime.
     */
    public function resetHookRereadsTheProvider(): void
    {
        $container = $this->container();
        $ab = $container->get(AbTesting::class);
        $ab->getRegistry()->all();

        $container->get(StateResetter::class)->reset();

        Assert::true($ab->getRegistry()->has('checkout'));
    }

    private function container(): Container
    {
        /** @var array<string, mixed> $definitions */
        $definitions = require __DIR__ . '/../config/di.php';

        $definitions[ExperimentProvider::class] = static fn(): ExperimentProvider => new ConfigExperimentProvider([
            'checkout' => [
                'enabled' => true,
                'salt' => 'checkout-v1',
                'fallbackVariant' => 'control',
                'variants' => ['control' => 50, 'green' => 50],
            ],
        ]);

        return new Container(ContainerConfig::create()->withDefinitions($definitions));
    }
}
