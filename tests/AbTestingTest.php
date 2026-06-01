<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidVariantException;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;

#[CoversClass(AbTesting::class)]
final class AbTestingTest extends TestCase
{
    private AbTesting $abTesting;

    /** @var list<Assignment> */
    private array $exposures = [];

    /** @var list<array{assignment: Assignment, goal: string}> */
    private array $conversions = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->exposures = [];
        $this->conversions = [];

        $provider = new ConfigExperimentProvider([
            'checkout-button' => [
                'enabled' => true,
                'salt' => 'checkout-v1',
                'fallbackVariant' => 'control',
                'variants' => ['control' => 50, 'green' => 50],
            ],
        ]);

        $exposureTracker = new class ($this->exposures) implements ExposureTracker {
            /** @param list<Assignment> $exposures */
            public function __construct(
                private array &$exposures,
            ) {}

            #[\Override]
            public function trackExposure(Assignment $assignment): void
            {
                $this->exposures[] = $assignment;
            }
        };

        $conversionTracker = new class ($this->conversions) implements ConversionTracker {
            /** @param list<array{assignment: Assignment, goal: string}> $conversions */
            public function __construct(
                private array &$conversions,
            ) {}

            #[\Override]
            public function trackConversion(Assignment $assignment, string $goal): void
            {
                $this->conversions[] = ['assignment' => $assignment, 'goal' => $goal];
            }
        };

        $this->abTesting = new AbTesting(
            provider: $provider,
            strategy: new WeightedHashAssignmentStrategy(),
            exposureTracker: $exposureTracker,
            conversionTracker: $conversionTracker,
        );
    }

    #[Test]
    public function assignReturnsDeterministicVariant(): void
    {
        $a1 = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $a2 = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $this->assertSame($a1->variant, $a2->variant);
    }

    #[Test]
    public function assignReturnsAssignmentWithCorrectFields(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $this->assertSame('checkout-button', $assignment->experiment);
        $this->assertSame('user-1', $assignment->subjectId);
        $this->assertFalse($assignment->isForced);
        $this->assertFalse($assignment->isFallback);
    }

    #[Test]
    public function forcedVariantReturnsCorrectAssignment(): void
    {
        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'green',
        );

        $this->assertSame('green', $assignment->variant);
        $this->assertTrue($assignment->isForced);
    }

    #[Test]
    public function unknownForcedVariantThrows(): void
    {
        $this->expectException(InvalidVariantException::class);

        $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'nonexistent',
        );
    }

    #[Test]
    public function disabledExperimentReturnsFallback(): void
    {
        $provider = new ConfigExperimentProvider([
            'disabled-test' => [
                'enabled' => false,
                'salt' => 'salt',
                'fallbackVariant' => 'control',
                'variants' => ['control' => 50, 'green' => 50],
            ],
        ]);
        $ab = new AbTesting(provider: $provider, strategy: new WeightedHashAssignmentStrategy());

        $assignment = $ab->assign(experiment: 'disabled-test', subjectId: 'user-1');

        $this->assertSame('control', $assignment->variant);
        $this->assertTrue($assignment->isFallback);
    }

    #[Test]
    public function isReturnsTrueForMatchingVariant(): void
    {
        $this->assertTrue($this->abTesting->is(
            experiment: 'checkout-button',
            variant: 'green',
            subjectId: 'user-1',
            forcedVariant: 'green',
        ));
    }

    #[Test]
    public function isReturnsFalseForNonMatchingVariant(): void
    {
        $this->assertFalse($this->abTesting->is(
            experiment: 'checkout-button',
            variant: 'control',
            subjectId: 'user-1',
            forcedVariant: 'green',
        ));
    }

    #[Test]
    public function assignDoesNotTriggerExposure(): void
    {
        $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $this->assertSame([], $this->exposures);
    }

    #[Test]
    public function trackExposureRecordsAssignment(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $this->abTesting->trackExposure($assignment);

        $this->assertCount(1, $this->exposures);
        $this->assertSame($assignment, $this->exposures[0]);
    }

    #[Test]
    public function trackConversionRecordsAssignmentAndGoal(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $this->abTesting->trackConversion($assignment, goal: 'purchase');

        $this->assertCount(1, $this->conversions);
        $this->assertSame($assignment, $this->conversions[0]['assignment']);
        $this->assertSame('purchase', $this->conversions[0]['goal']);
    }

    #[Test]
    public function getRegistryExposesConfiguredExperiment(): void
    {
        $this->assertTrue($this->abTesting->getRegistry()->has('checkout-button'));
    }

    #[Test]
    public function assignCarriesContext(): void
    {
        $context = AssignmentContext::forEnvironment('production');

        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            context: $context,
        );

        $this->assertSame($context, $assignment->context);
    }

    #[Test]
    public function forcedAssignmentCarriesContext(): void
    {
        $context = AssignmentContext::forEnvironment('staging');

        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'green',
            context: $context,
        );

        $this->assertSame($context, $assignment->context);
    }

    #[Test]
    public function fallbackAssignmentCarriesContext(): void
    {
        $context = AssignmentContext::forEnvironment('production');
        $provider = new ConfigExperimentProvider([
            'disabled-test' => [
                'enabled' => false,
                'salt' => 'salt',
                'fallbackVariant' => 'control',
                'variants' => ['control' => 50, 'green' => 50],
            ],
        ]);
        $ab = new AbTesting(provider: $provider, strategy: new WeightedHashAssignmentStrategy());

        $assignment = $ab->assign(experiment: 'disabled-test', subjectId: 'user-1', context: $context);

        $this->assertSame($context, $assignment->context);
    }
}
