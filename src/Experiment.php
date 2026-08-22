<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class Experiment
{
    private const string NAME_PATTERN = '/^[a-z][a-z0-9_-]*\z/';

    public string $name;

    public string $salt;

    public string $fallbackVariant;

    /**
     * @var array<string, int<0, max>>
     */
    public array $variants;

    /**
     * Weights are declared as `mixed` and narrowed here on purpose.
     *
     * `int<0, max>` is a Psalm annotation, and `ConfigExperimentProvider` hands
     * application `params` straight through — so a negative, fractional or
     * numeric-string weight from a config typo reaches this constructor at
     * runtime no matter what the docblock says. A negative weight is the
     * dangerous one: `array_sum()` still clears the `> 0` gate, but the
     * cumulative bucket boundary in `WeightedHashAssignmentStrategy` goes
     * backwards and the variant before it becomes unreachable, so the
     * experiment silently runs a distribution nobody configured.
     *
     * Validating in the value object covers every provider at once. Zero stays
     * valid: it is the documented way to keep a variant defined while routing
     * no traffic to it, and the total-weight check already rejects all-zero.
     *
     * @param array<string, mixed> $variants
     */
    public function __construct(
        string $name,
        public bool $enabled,
        string $salt,
        string $fallbackVariant,
        array $variants,
        public ?TargetingRule $targeting = null,
        public ?string $configurationId = null,
    ) {
        $this->validateName($name, 'experiment');

        if ($salt === '') {
            throw new Exception\InvalidExperimentException(
                message: sprintf('Salt must not be empty in experiment "%s"', $name),
            );
        }

        if ($variants === []) {
            throw new Exception\InvalidExperimentException(
                message: sprintf('Experiment "%s" must have at least one variant', $name),
            );
        }

        $validated = [];

        foreach ($variants as $variantName => $weight) {
            $this->validateName($variantName, 'variant');

            if (!\is_int($weight)) {
                throw new Exception\InvalidExperimentException(
                    message: sprintf(
                        'Weight of variant "%s" in experiment "%s" must be an integer, got %s',
                        $variantName,
                        $name,
                        get_debug_type($weight),
                    ),
                );
            }

            if ($weight < 0) {
                throw new Exception\InvalidExperimentException(
                    message: sprintf(
                        'Weight of variant "%s" in experiment "%s" must not be negative, got %d',
                        $variantName,
                        $name,
                        $weight,
                    ),
                );
            }

            $validated[$variantName] = $weight;
        }

        if (!isset($validated[$fallbackVariant])) {
            throw new Exception\InvalidExperimentException(
                message: sprintf(
                    'Fallback variant "%s" does not exist in experiment "%s"',
                    $fallbackVariant,
                    $name,
                ),
            );
        }

        $totalWeight = array_sum($validated);

        if ($totalWeight <= 0) {
            throw new Exception\InvalidExperimentException(
                message: sprintf('Total weight must be greater than 0 in experiment "%s"', $name),
            );
        }

        if ($configurationId === '') {
            throw new Exception\InvalidExperimentException(
                message: sprintf('Configuration ID must not be empty in experiment "%s"', $name),
            );
        }

        $this->name = $name;
        $this->salt = $salt;
        $this->fallbackVariant = $fallbackVariant;
        $this->variants = $validated;
    }

    private function validateName(string $name, string $type): void
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            throw $type === 'experiment'
                ? new Exception\InvalidExperimentException(
                    message: sprintf('Invalid %s name "%s"', $type, $name),
                )
                : new Exception\InvalidVariantException(
                    message: sprintf('Invalid %s name "%s"', $type, $name),
                );
        }
    }
}
