<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\Exception\InvalidExperimentException;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Rasuvaeff\Yii3AbTesting\ExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ExperimentRegistry;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(ExperimentRegistry::class)]
final class ExperimentRegistryTest
{
    /** @param list<Experiment> $experiments */
    private function registryOf(array $experiments): ExperimentRegistry
    {
        $provider = new readonly class ($experiments) implements ExperimentProvider {
            /** @param list<Experiment> $experiments */
            public function __construct(
                private array $experiments,
            ) {}

            #[\Override]
            public function getExperiments(): array
            {
                $result = [];

                foreach ($this->experiments as $experiment) {
                    $result[$experiment->name] = $experiment;
                }

                return $result;
            }
        };

        return new ExperimentRegistry(provider: $provider);
    }

    public function getReturnsExperiment(): void
    {
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );
        $registry = $this->registryOf([$exp]);

        Assert::same($registry->get('test'), $exp);
    }

    public function getThrowsOnUnknownExperiment(): void
    {
        $registry = $this->registryOf([]);

        Expect::exception(InvalidExperimentException::class);

        $registry->get('unknown');
    }

    public function hasReturnsCorrectBool(): void
    {
        $exp = new Experiment(
            name: 'test',
            enabled: true,
            salt: 'salt',
            fallbackVariant: 'a',
            variants: ['a' => 100],
        );
        $registry = $this->registryOf([$exp]);

        Assert::true($registry->has('test'));
        Assert::false($registry->has('other'));
    }

    public function allReturnsAllExperiments(): void
    {
        $exp1 = new Experiment(name: 'a', enabled: true, salt: 's1', fallbackVariant: 'x', variants: ['x' => 100]);
        $exp2 = new Experiment(name: 'b', enabled: true, salt: 's2', fallbackVariant: 'y', variants: ['y' => 100]);
        $registry = $this->registryOf([$exp1, $exp2]);

        Assert::count($registry->all(), 2);
    }

    public function providerIsNotQueriedUntilFirstAccess(): void
    {
        $provider = $this->countingProvider();
        new ExperimentRegistry(provider: $provider);

        Assert::same($provider->calls, 0);
    }

    public function providerIsQueriedOnceAcrossAccesses(): void
    {
        $provider = $this->countingProvider();
        $registry = new ExperimentRegistry(provider: $provider);

        $registry->all();
        $registry->has('test');
        $registry->get('test');

        Assert::same($provider->calls, 1);
    }

    public function resetRereadsProvider(): void
    {
        $provider = $this->countingProvider();
        $registry = new ExperimentRegistry(provider: $provider);

        $registry->all();
        $registry->reset();
        $registry->all();

        Assert::same($provider->calls, 2);
    }

    private function countingProvider(): ExperimentProvider
    {
        return new class implements ExperimentProvider {
            public int $calls = 0;

            #[\Override]
            public function getExperiments(): array
            {
                ++$this->calls;

                return [
                    'test' => new Experiment(
                        name: 'test',
                        enabled: true,
                        salt: 'salt',
                        fallbackVariant: 'a',
                        variants: ['a' => 100],
                    ),
                ];
            }
        };
    }
}
