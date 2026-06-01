<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class AbTesting
{
    private ExperimentRegistry $registry;

    public function __construct(
        ExperimentProvider $provider,
        private AssignmentStrategy $strategy,
        private ExposureTracker $exposureTracker = new NullExposureTracker(),
        private ConversionTracker $conversionTracker = new NullConversionTracker(),
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
                isForced: true,
                context: $context,
            );
        }

        if (!$exp->enabled) {
            return new Assignment(
                experiment: $experiment,
                variant: $exp->fallbackVariant,
                subjectId: $subjectId,
                isFallback: true,
                context: $context,
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

    public function trackExposure(Assignment $assignment): void
    {
        $this->exposureTracker->trackExposure($assignment);
    }

    public function trackConversion(Assignment $assignment, string $goal): void
    {
        $this->conversionTracker->trackConversion($assignment, goal: $goal);
    }

    public function getRegistry(): ExperimentRegistry
    {
        return $this->registry;
    }
}
