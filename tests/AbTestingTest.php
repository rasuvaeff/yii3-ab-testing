<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3AbTesting\AbTesting;
use Rasuvaeff\Yii3AbTesting\AllowListAnalyticsContextPolicy;
use Rasuvaeff\Yii3AbTesting\AssignmentContext;
use Rasuvaeff\Yii3AbTesting\AssignmentReceipt;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\AttributeTargetingRule;
use Rasuvaeff\Yii3AbTesting\ConfigExperimentProvider;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\EnvironmentTargetingRule;
use Rasuvaeff\Yii3AbTesting\Exception\InvalidVariantException;
use Rasuvaeff\Yii3AbTesting\Experiment;
use Rasuvaeff\Yii3AbTesting\ExperimentProvider;
use Rasuvaeff\Yii3AbTesting\TargetingRule;
use Rasuvaeff\Yii3AbTesting\Tests\Support\FixedClock;
use Rasuvaeff\Yii3AbTesting\Tests\Support\RecordingConversionTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\RecordingExposureTracker;
use Rasuvaeff\Yii3AbTesting\Tests\Support\SequentialEventIdGenerator;
use Rasuvaeff\Yii3AbTesting\WeightedHashAssignmentStrategy;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(AbTesting::class)]
final class AbTestingTest
{
    private AbTesting $abTesting;

    private RecordingExposureTracker $exposures;

    private RecordingConversionTracker $conversions;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->exposures = new RecordingExposureTracker();
        $this->conversions = new RecordingConversionTracker();

        $this->abTesting = new AbTesting(
            provider: new ConfigExperimentProvider([
                'checkout-button' => [
                    'enabled' => true,
                    'salt' => 'checkout-v1',
                    'fallbackVariant' => 'control',
                    'variants' => ['control' => 50, 'green' => 50],
                ],
            ]),
            strategy: new WeightedHashAssignmentStrategy(),
            exposureTracker: $this->exposures,
            conversionTracker: $this->conversions,
            clock: new FixedClock(),
            eventIds: new SequentialEventIdGenerator(),
            contextPolicy: new AllowListAnalyticsContextPolicy(allowedAttributes: ['country']),
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
        Assert::same($assignment->reason, DecisionReason::Assigned);
        Assert::same($assignment->source, AssignmentSource::Computed);
    }

    public function forcedVariantReturnsCorrectAssignment(): void
    {
        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'green',
        );

        Assert::same($assignment->variant, 'green');
        Assert::same($assignment->reason, DecisionReason::Forced);
        Assert::same(
            $assignment->configurationId,
            $this->abTesting->getRegistry()->get('checkout-button')->configurationId,
        );
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
        $ab = $this->abTestingForDisabledExperiment();

        $assignment = $ab->assign(experiment: 'disabled-test', subjectId: 'user-1');

        Assert::same($assignment->variant, 'control');
        Assert::same($assignment->reason, DecisionReason::FallbackDisabled);
        Assert::same(
            $assignment->configurationId,
            $ab->getRegistry()->get('disabled-test')->configurationId,
        );
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

    public function resolveImplementsAssignmentResolverContract(): void
    {
        $assignment = $this->abTesting->resolve(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            forcedVariant: 'green',
        );

        Assert::same($assignment->variant, 'green');
        Assert::same($assignment->reason, DecisionReason::Forced);
    }

    public function isWithContextEvaluatesTargeting(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );

        Assert::true($ab->isWithContext(
            experiment: 'targeted',
            variant: 'control',
            subjectId: 'user-1',
            context: AssignmentContext::forEnvironment('staging'),
        ));
        Assert::false($ab->isWithContext(
            experiment: 'targeted',
            variant: 'control',
            subjectId: 'user-1',
            context: AssignmentContext::forEnvironment('production'),
            forcedVariant: 'green',
        ));
    }

    public function assignDoesNotTriggerExposure(): void
    {
        $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        Assert::same($this->exposures->events, []);
    }

    public function trackExposureMintsIdentityAndTimestamp(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $event = $this->abTesting->trackExposure($assignment);

        Assert::count($this->exposures->events, 1);
        Assert::same($this->exposures->events[0], $event);
        Assert::same($event->eventId, 'evt-1');
        Assert::same($event->occurredAt->format('Y-m-d H:i:s.v'), '2026-08-01 10:00:00.123');
        Assert::same($event->experiment, 'checkout-button');
        Assert::same($event->subjectId, 'user-1');
        Assert::same($event->reason, DecisionReason::Assigned);
        Assert::same($event->source, AssignmentSource::Computed);
    }

    public function everyExposureGetsItsOwnIdentity(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $first = $this->abTesting->trackExposure($assignment);
        $second = $this->abTesting->trackExposure($assignment);

        Assert::same([$first->eventId, $second->eventId], ['evt-1', 'evt-2']);
    }

    public function exposureCarriesRevisionEnvironmentAndAllowedDimensions(): void
    {
        $context = AssignmentContext::forEnvironment('production')
            ->withAttribute('country', 'RU')
            ->withAttribute('secret', 'leak');
        $assignment = $this->abTesting->assign(
            experiment: 'checkout-button',
            subjectId: 'user-1',
            context: $context,
        );

        $event = $this->abTesting->trackExposure($assignment);

        Assert::same($event->environment, 'production');
        Assert::same($event->dimensions, ['country' => 'RU']);
        Assert::same($event->experimentRevision, $assignment->configurationId);
    }

    public function exposureWithoutContextHasEmptyEnvironmentAndDimensions(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $event = $this->abTesting->trackExposure($assignment);

        Assert::same($event->environment, '');
        Assert::same($event->dimensions, []);
    }

    public function targetingMismatchIsDistinguishableInTheEvent(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );
        $assignment = $ab->assign(
            experiment: 'targeted',
            subjectId: 'user-1',
            context: AssignmentContext::forEnvironment('staging'),
        );

        $event = $ab->trackExposure($assignment);

        Assert::same($event->reason, DecisionReason::FallbackTargetingMismatch);
        Assert::false($event->isAnalyzable());
    }

    public function trackConversionRecordsGoalAndDecision(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $event = $this->abTesting->trackConversion($assignment, goal: 'purchase');

        Assert::count($this->conversions->events, 1);
        Assert::same($this->conversions->events[0], $event);
        Assert::same($event->goal, 'purchase');
        Assert::same($event->reason, $assignment->reason);
        Assert::null($event->exposureEventId);
    }

    public function conversionLinksToTheExposureOfTheSameRequest(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $exposure = $this->abTesting->trackExposure($assignment);

        $conversion = $this->abTesting->trackConversion($assignment, goal: 'purchase', exposure: $exposure);

        Assert::same($conversion->exposureEventId, $exposure->eventId);
    }

    public function conversionForReceiptKeepsTheOriginalDecision(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');
        $receipt = $this->abTesting->trackExposure($assignment)->receipt();

        $conversion = $this->abTesting->trackConversionForReceipt($receipt, goal: 'purchase');

        Assert::same($conversion->experiment, $receipt->experiment);
        Assert::same($conversion->variant, $receipt->variant);
        Assert::same($conversion->subjectId, $receipt->subjectId);
        Assert::same($conversion->reason, $receipt->reason);
        Assert::same($conversion->source, $receipt->source);
        Assert::same($conversion->experimentRevision, $receipt->experimentRevision);
        Assert::same($conversion->exposureEventId, 'evt-1');
        Assert::same($conversion->eventId, 'evt-2');
    }

    public function conversionForReceiptUsesTheConversionRequestContext(): void
    {
        $receipt = new AssignmentReceipt(
            exposureEventId: 'evt-old',
            occurredAt: new \DateTimeImmutable('2026-07-01 00:00:00', new \DateTimeZone('UTC')),
            experiment: 'checkout-button',
            variant: 'green',
            subjectId: 'user-1',
            reason: DecisionReason::Assigned,
            source: AssignmentSource::Store,
        );
        $context = AssignmentContext::forEnvironment('production')->withAttribute('country', 'DE');

        $conversion = $this->abTesting->trackConversionForReceipt($receipt, goal: 'purchase', context: $context);

        Assert::same($conversion->environment, 'production');
        Assert::same($conversion->dimensions, ['country' => 'DE']);
        Assert::same($conversion->occurredAt->format('Y-m-d H:i:s.v'), '2026-08-01 10:00:00.123');
    }

    public function conversionForReceiptWithoutContextHasNoDimensions(): void
    {
        $receipt = $this->abTesting
            ->trackExposure($this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1'))
            ->receipt();

        $conversion = $this->abTesting->trackConversionForReceipt($receipt, goal: 'purchase');

        Assert::same($conversion->environment, '');
        Assert::same($conversion->dimensions, []);
    }

    #[DataProvider('emptyConversionGoalProvider')]
    public function trackConversionRejectsEmptyGoal(string $goal): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        Expect::exception(InvalidArgumentException::class)
            ->withMessage('Event field "goal" must not be empty');

        $this->abTesting->trackConversion($assignment, goal: $goal);
    }

    public static function emptyConversionGoalProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'whitespace' => ["\t\n"];
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
        Assert::true(is_string($assignment->configurationId));
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
        $ab = $this->abTestingForDisabledExperiment();

        $assignment = $ab->assign(experiment: 'disabled-test', subjectId: 'user-1', context: $context);

        Assert::same($assignment->context, $context);
    }

    public function targetingMismatchReturnsFallbackWithReason(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );
        $context = AssignmentContext::forEnvironment('staging');

        $assignment = $ab->assign(experiment: 'targeted', subjectId: 'user-1', context: $context);

        Assert::same($assignment->variant, 'control');
        Assert::same($assignment->reason, DecisionReason::FallbackTargetingMismatch);
        Assert::same($assignment->configurationId, 'targeting-revision');
    }

    public function targetingMatchProceedsToNormalAssignment(): void
    {
        $ab = $this->abTestingWithTargeting(
            new EnvironmentTargetingRule(environments: ['production']),
        );
        $context = AssignmentContext::forEnvironment('production');

        $assignment = $ab->assign(experiment: 'targeted', subjectId: 'user-1', context: $context);

        Assert::same($assignment->reason, DecisionReason::Assigned);
        Assert::true(in_array($assignment->variant, ['control', 'green'], true));
    }

    public function noTargetingAssignsAllSubjects(): void
    {
        $assignment = $this->abTesting->assign(experiment: 'checkout-button', subjectId: 'user-1');

        Assert::same($assignment->reason, DecisionReason::Assigned);
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
        Assert::same($assignment->reason, DecisionReason::Forced);
    }

    public function disabledExperimentBypassesTargetingCheck(): void
    {
        $rule = new AttributeTargetingRule(attribute: 'plan', value: 'pro');
        $provider = new readonly class ($rule) implements ExperimentProvider {
            public function __construct(private AttributeTargetingRule $rule) {}

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

        Assert::same($assignment->reason, DecisionReason::FallbackDisabled);
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

    public function defaultsWorkWithoutAnyWiring(): void
    {
        $ab = new AbTesting(
            provider: new ConfigExperimentProvider([
                'checkout-button' => [
                    'enabled' => true,
                    'salt' => 'checkout-v1',
                    'fallbackVariant' => 'control',
                    'variants' => ['control' => 50, 'green' => 50],
                ],
            ]),
            strategy: new WeightedHashAssignmentStrategy(),
        );
        $assignment = $ab->assign(experiment: 'checkout-button', subjectId: 'user-1');

        $event = $ab->trackExposure($assignment);

        Assert::same(\strlen($event->eventId), 36);
        Assert::same($event->dimensions, []);
    }

    private function abTestingForDisabledExperiment(): AbTesting
    {
        return new AbTesting(
            provider: new ConfigExperimentProvider([
                'disabled-test' => [
                    'enabled' => false,
                    'salt' => 'salt',
                    'fallbackVariant' => 'control',
                    'variants' => ['control' => 50, 'green' => 50],
                ],
            ]),
            strategy: new WeightedHashAssignmentStrategy(),
        );
    }

    private function abTestingWithTargeting(
        TargetingRule $targeting,
    ): AbTesting {
        $provider = new readonly class ($targeting) implements ExperimentProvider {
            public function __construct(private TargetingRule $targeting) {}

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
                        configurationId: 'targeting-revision',
                    ),
                ];
            }
        };

        return new AbTesting(
            provider: $provider,
            strategy: new WeightedHashAssignmentStrategy(),
            clock: new FixedClock(),
            eventIds: new SequentialEventIdGenerator(),
        );
    }
}
