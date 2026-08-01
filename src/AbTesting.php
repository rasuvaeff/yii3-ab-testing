<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use Psr\Clock\ClockInterface;

/**
 * Entry point: resolves subjects into variants and turns those decisions into
 * analytics events.
 *
 * Event identity, timestamp and the context allow-list are applied here, once,
 * so every delivery path serializes the same fields. Sinks receive a finished
 * event and only transport it.
 *
 * `assign()` and `is()` remain free of side effects — exposure stays an explicit
 * act, because auto-tracking would invent impressions on prefetch, on hidden
 * branches and on repeated calls.
 *
 * @api
 */
final readonly class AbTesting implements AssignmentResolver
{
    private ExperimentRegistry $registry;

    public function __construct(
        ExperimentProvider $provider,
        private AssignmentStrategy $strategy,
        private ExposureTracker $exposureTracker = new NullExposureTracker(),
        private ConversionTracker $conversionTracker = new NullConversionTracker(),
        private ClockInterface $clock = new SystemClock(),
        private EventIdGenerator $eventIds = new Uuid7EventIdGenerator(),
        private AnalyticsContextPolicy $contextPolicy = new AllowListAnalyticsContextPolicy(),
    ) {
        $this->registry = new ExperimentRegistry(provider: $provider);
    }

    public function assign(
        string $experiment,
        string $subjectId,
        ?string $forcedVariant = null,
        ?AssignmentContext $context = null,
    ): Assignment {
        $exp = $this->registry->get($experiment);

        if ($forcedVariant !== null) {
            if (!isset($exp->variants[$forcedVariant])) {
                throw new Exception\InvalidVariantException(
                    message: sprintf(
                        'Unknown variant "%s" in experiment "%s"',
                        $forcedVariant,
                        $experiment,
                    ),
                );
            }

            return new Assignment(
                experiment: $experiment,
                variant: $forcedVariant,
                subjectId: $subjectId,
                reason: DecisionReason::Forced,
                context: $context,
                configurationId: $exp->configurationId,
            );
        }

        if (!$exp->enabled) {
            return new Assignment(
                experiment: $experiment,
                variant: $exp->fallbackVariant,
                subjectId: $subjectId,
                reason: DecisionReason::FallbackDisabled,
                context: $context,
                configurationId: $exp->configurationId,
            );
        }

        if ($exp->targeting instanceof TargetingRule
            && !$exp->targeting->matches($context ?? AssignmentContext::empty())) {
            return new Assignment(
                experiment: $experiment,
                variant: $exp->fallbackVariant,
                subjectId: $subjectId,
                reason: DecisionReason::FallbackTargetingMismatch,
                context: $context,
                configurationId: $exp->configurationId,
            );
        }

        $variant = $this->strategy->assign(
            salt: $exp->salt,
            subjectId: $subjectId,
            variants: $exp->variants,
        );

        return new Assignment(
            experiment: $experiment,
            variant: $variant,
            subjectId: $subjectId,
            context: $context,
            configurationId: $exp->configurationId,
        );
    }

    #[\Override]
    public function resolve(
        string $experiment,
        string $subjectId,
        ?string $forcedVariant = null,
        ?AssignmentContext $context = null,
    ): Assignment {
        return $this->assign(
            experiment: $experiment,
            subjectId: $subjectId,
            forcedVariant: $forcedVariant,
            context: $context,
        );
    }

    public function is(
        string $experiment,
        string $variant,
        string $subjectId,
        ?string $forcedVariant = null,
    ): bool {
        return $this->assign(
            experiment: $experiment,
            subjectId: $subjectId,
            forcedVariant: $forcedVariant,
        )->isVariant($variant);
    }

    public function isWithContext(
        string $experiment,
        string $variant,
        string $subjectId,
        AssignmentContext $context,
        ?string $forcedVariant = null,
    ): bool {
        return $this->assign(
            experiment: $experiment,
            subjectId: $subjectId,
            forcedVariant: $forcedVariant,
            context: $context,
        )->isVariant($variant);
    }

    /**
     * Records that the subject saw the variant.
     *
     * Returns the event so the caller can keep its identity — typically as
     * `$event->receipt()` stored in a cookie or session, to attribute a
     * conversion that happens in a later request.
     */
    public function trackExposure(Assignment $assignment): ExposureEvent
    {
        $event = new ExposureEvent(
            eventId: $this->eventIds->generate(),
            occurredAt: $this->clock->now(),
            experiment: $assignment->experiment,
            variant: $assignment->variant,
            subjectId: $assignment->subjectId,
            reason: $assignment->reason,
            source: $assignment->source,
            experimentRevision: $assignment->configurationId,
            environment: $assignment->context?->getEnvironment() ?? '',
            dimensions: $this->contextPolicy->apply($assignment->context),
        );

        $this->exposureTracker->trackExposure($event);

        return $event;
    }

    /**
     * Records a conversion for an assignment resolved in this request.
     *
     * Pass `$exposure` when the exposure happened in the same request, so the
     * conversion is attributed to it exactly instead of by attribution window.
     */
    public function trackConversion(
        Assignment $assignment,
        string $goal,
        ?ExposureEvent $exposure = null,
    ): ConversionEvent {
        $event = new ConversionEvent(
            eventId: $this->eventIds->generate(),
            occurredAt: $this->clock->now(),
            experiment: $assignment->experiment,
            variant: $assignment->variant,
            subjectId: $assignment->subjectId,
            goal: $goal,
            reason: $assignment->reason,
            source: $assignment->source,
            experimentRevision: $assignment->configurationId,
            environment: $assignment->context?->getEnvironment() ?? '',
            dimensions: $this->contextPolicy->apply($assignment->context),
            exposureEventId: $exposure?->eventId,
        );

        $this->conversionTracker->trackConversion($event);

        return $event;
    }

    /**
     * Records a conversion for an exposure from an earlier request.
     *
     * The receipt carries the decision as it was, so a reweight or a salt
     * change between the two requests cannot silently re-attribute the
     * conversion to a different variant. `$context` describes the conversion
     * request, not the original exposure.
     */
    public function trackConversionForReceipt(
        AssignmentReceipt $receipt,
        string $goal,
        ?AssignmentContext $context = null,
    ): ConversionEvent {
        $event = new ConversionEvent(
            eventId: $this->eventIds->generate(),
            occurredAt: $this->clock->now(),
            experiment: $receipt->experiment,
            variant: $receipt->variant,
            subjectId: $receipt->subjectId,
            goal: $goal,
            reason: $receipt->reason,
            source: $receipt->source,
            experimentRevision: $receipt->experimentRevision,
            environment: $context?->getEnvironment() ?? '',
            dimensions: $this->contextPolicy->apply($context),
            exposureEventId: $receipt->exposureEventId,
        );

        $this->conversionTracker->trackConversion($event);

        return $event;
    }

    public function getRegistry(): ExperimentRegistry
    {
        return $this->registry;
    }
}
