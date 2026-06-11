<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidExperimentException;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Rasuvaeff\Yii3AbTesting\ExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ExperimentRegistry;

#[CoversClass(ExperimentRegistry::class)]
final class ExperimentRegistryTest extends TestCase
{
    /**
     * @param list<Experiment> $experiments
     */
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

    #[Test]
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

        $this->assertSame($exp, $registry->get('test'));
    }

    #[Test]
    public function getThrowsOnUnknownExperiment(): void
    {
        $registry = $this->registryOf([]);

        $this->expectException(InvalidExperimentException::class);

        $registry->get('unknown');
    }

    #[Test]
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

        $this->assertTrue($registry->has('test'));
        $this->assertFalse($registry->has('other'));
    }

    #[Test]
    public function allReturnsAllExperiments(): void
    {
        $exp1 = new Experiment(name: 'a', enabled: true, salt: 's1', fallbackVariant: 'x', variants: ['x' => 100]);
        $exp2 = new Experiment(name: 'b', enabled: true, salt: 's2', fallbackVariant: 'y', variants: ['y' => 100]);
        $registry = $this->registryOf([$exp1, $exp2]);

        $this->assertCount(2, $registry->all());
    }

    #[Test]
    public function providerIsNotQueriedUntilFirstAccess(): void
    {
        $provider = $this->countingProvider();
        new ExperimentRegistry(provider: $provider);

        $this->assertSame(0, $provider->calls);
    }

    #[Test]
    public function providerIsQueriedOnceAcrossAccesses(): void
    {
        $provider = $this->countingProvider();
        $registry = new ExperimentRegistry(provider: $provider);

        $registry->all();
        $registry->has('test');
        $registry->get('test');

        $this->assertSame(1, $provider->calls);
    }

    #[Test]
    public function resetRereadsProvider(): void
    {
        $provider = $this->countingProvider();
        $registry = new ExperimentRegistry(provider: $provider);

        $registry->all();
        $registry->reset();
        $registry->all();

        $this->assertSame(2, $provider->calls);
    }

    /**
     * @return ExperimentProvider&object{calls: int}
     */
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
