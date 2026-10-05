<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidExperimentException;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Rasuvaeff\Yii3AbTesting\ExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ExperimentRegistry;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(ExperimentRegistry::class)]
#[Covers(InvalidExperimentException::class)]
final class ExperimentRegistryTest
{
    /** @param list<Experiment> $experiments */
    private function registryOf(array $experiments): ExperimentRegistry
    {
        $indexed = [];

        foreach ($experiments as $experiment) {
            $indexed[$experiment->name] = $experiment;
        }

        $provider = Understudy::for(ExperimentProvider::class);
        when(fn() => $provider->getExperiments())->returns($indexed);

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
        $provider = $this->provider();
        new ExperimentRegistry(provider: $provider);

        verify(fn() => $provider->getExperiments(), never: true);
    }

    public function providerIsQueriedOnceAcrossAccesses(): void
    {
        $provider = $this->provider();
        $registry = new ExperimentRegistry(provider: $provider);

        $registry->all();
        $registry->has('test');
        $registry->get('test');

        verify(fn() => $provider->getExperiments(), times: 1);
    }

    public function resetRereadsProvider(): void
    {
        $provider = $this->provider();
        $registry = new ExperimentRegistry(provider: $provider);

        $registry->all();
        $registry->reset();
        $registry->all();

        verify(fn() => $provider->getExperiments(), times: 2);
    }

    private function provider(): ExperimentProvider
    {
        $provider = Understudy::for(ExperimentProvider::class);
        when(fn() => $provider->getExperiments())->returns([
            'test' => new Experiment(
                name: 'test',
                enabled: true,
                salt: 'salt',
                fallbackVariant: 'a',
                variants: ['a' => 100],
            ),
        ]);

        return $provider;
    }
}
