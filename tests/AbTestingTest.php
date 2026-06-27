<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\Assignment;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ConversionTracker;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidVariantException;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Rasuvaeff\Yii3AbTesting\ExperimentProvider;
use Rasuvaeff\Yii3AbTesting\ExposureTracker;
use Rasuvaeff\Yii3AbTesting\TargetingRule;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(AbTesting::class)]
final class AbTestingTest
{
    private AbTesting $abTesting;

    /** @var list<Assignment> */
    private array $exposures = [];

    /** @var list<array{assignment: Assignment, goal: string}> */
    private array $conversions = [];

    #[BeforeTest]
    public function setUp(): void
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

        $exposures = &$this->exposures;
        $exposureTracker = new class ($exposures) implements ExposureTracker {
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

        $conversions = &$this->conversions;
        $conversionTracker = new class ($conversions) implements ConversionTracker {
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

    public function assignReturnsDeterministicVariant(): void
    {
        $a1 = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $a2 = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        Assert::same($a1->variant, $a2->variant);
    }

    public function assignReturnsAssignmentWithCorrectFields(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        Assert::same($assignment->experiment, 'checkout-button');
        Assert::same($assignment->subjectId, 'user-1');
        Assert::false($assignment->isForced);
        Assert::false($assignment->isFallback);
    }

    public function forcedVariantReturnsCorrectAssignment(): void
    {
        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'green',
        );

        Assert::same($assignment->variant, 'green');
        Assert::true($assignment->isForced);
    }

    public function unknownForcedVariantThrows(): void
    {
        Expect::exception(InvalidVariantException::class);

        $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'nonexistent',
        );
    }

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

        Assert::same($assignment->variant, 'control');
        Assert::true($assignment->isFallback);
    }

    public function isReturnsTrueForMatchingVariant(): void
    {
        Assert::true($this->abTesting->is(
            experiment: 'checkout-button',
            variant: 'green',
            subjectId: 'user-1',
            forcedVariant: 'green',
        ));
    }

    public function isReturnsFalseForNonMatchingVariant(): void
    {
        Assert::false($this->abTesting->is(
            experiment: 'checkout-button',
            variant: 'control',
            subjectId: 'user-1',
            forcedVariant: 'green',
        ));
    }

    public function assignDoesNotTriggerExposure(): void
    {
        $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        Assert::same($this->exposures, []);
    }

    public function trackExposureRecordsAssignment(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $this->abTesting->trackExposure($assignment);

        Assert::count($this->exposures, 1);
        Assert::same($this->exposures[0], $assignment);
    }

    public function trackConversionRecordsAssignmentAndGoal(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $this->abTesting->trackConversion($assignment, goal: 'purchase');

        Assert::count($this->conversions, 1);
        Assert::same($this->conversions[0]['assignment'], $assignment);
        Assert::same($this->conversions[0]['goal'], 'purchase');
    }

    public function getRegistryExposesConfiguredExperiment(): void
    {
        Assert::true($this->abTesting->getRegistry()->has('checkout-button'));
    }

    public function assignCarriesContext(): void
    {
        $context = AssignmentContext::forEnvironment('production');

        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            context: $context,
        );

        Assert::same($assignment->context, $context);
    }

    public function forcedAssignmentCarriesContext(): void
    {
        $context = AssignmentContext::forEnvironment('staging');

        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'green',
            context: $context,
        );

        Assert::same($assignment->context, $context);
    }

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

        Assert::same($assignment->context, $context);
    }

    public function targetingMismatchReturnsFallbackWithFlag(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );
        $context = AssignmentContext::forEnvironment('staging');

        $assignment = $ab->assign(experiment: 'targeted', subjectId: 'user-1', context: $context);

        Assert::same($assignment->variant, 'control');
        Assert::true($assignment->isFallback);
        Assert::true($assignment->isTargetingMismatch);
    }

    public function targetingMatchProceedsToNormalAssignment(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );
        $context = AssignmentContext::forEnvironment('production');

        $assignment = $ab->assign(experiment: 'targeted', subjectId: 'user-1', context: $context);

        Assert::false($assignment->isFallback);
        Assert::false($assignment->isTargetingMismatch);
        Assert::true(in_array($assignment->variant, ['control', 'green'], true));
    }

    public function noTargetingAssignsAllSubjects(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        Assert::false($assignment->isFallback);
        Assert::false($assignment->isTargetingMismatch);
    }

    public function forcedVariantBypassesTargeting(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );
        $context = AssignmentContext::forEnvironment('staging');

        $assignment = $ab->assign(
            experiment: 'targeted',
            subjectId: 'user-1',
            forcedVariant: 'green',
            context: $context,
        );

        Assert::same($assignment->variant, 'green');
        Assert::true($assignment->isForced);
        Assert::false($assignment->isTargetingMismatch);
    }

    public function disabledExperimentBypassesTargetingCheck(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'plan', value: 'pro');
        $provider = new class ($rule) implements ExperimentProvider {
            public function __construct(private readonly AttributeTargetingRule $rule) {}

            #[\Override]
            public function getExperiments(): array
            {
                return [
                    'targeted' => new Experiment(
                        name: 'targeted',
                        enabled: false,
                        salt: 'salt',
                        fallbackVariant: 'control',
                        variants: ['control' => 50, 'green' => 50],
                        targeting: $this->rule,
                    ),
                ];
            }
        };
        $ab = new AbTesting(provider: $provider, strategy: new WeightedHashAssignmentStrategy());

        $assignment = $ab->assign(experiment: 'targeted', subjectId: 'user-1');

        Assert::true($assignment->isFallback);
        Assert::false($assignment->isTargetingMismatch);
    }

    public function targetingMismatchCarriesContext(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );
        $context = AssignmentContext::forEnvironment('staging');

        $assignment = $ab->assign(experiment: 'targeted', subjectId: 'user-1', context: $context);

        Assert::same($assignment->context, $context);
    }

    private function abTestingWithTargeting(
        TargetingRule $targeting,
    ): AbTesting {
        $provider = new class ($targeting) implements ExperimentProvider {
            public function __construct(private readonly TargetingRule $targeting) {}

            #[\Override]
            public function getExperiments(): array
            {
                return [
                    'targeted' => new Experiment(
                        name: 'targeted',
                        enabled: true,
                        salt: 'salt',
                        fallbackVariant: 'control',
                        variants: ['control' => 50, 'green' => 50],
                        targeting: $this->targeting,
                    ),
                ];
            }
        };

        return new AbTesting(provider: $provider, strategy: new WeightedHashAssignmentStrategy());
    }
}
