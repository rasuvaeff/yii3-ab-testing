<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * @api
 */
final readonly class Experiment
{
    private const string NAME_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    public string $name;

    public string $salt;

    public string $fallbackVariant;

    /**
     * @var array<string, int<0, max>>
     */
    public array $variants;

    /**
     * @param array<string, int<0, max>> $variants
     */
    public function __construct(
        string $name,
        public bool $enabled,
        string $salt,
        string $fallbackVariant,
        array $variants,
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

        foreach (array_keys($variants) as $variantName) {
            $this->validateName($variantName, 'variant');
        }

        if (!isset($variants[$fallbackVariant])) {
            throw new Exception\InvalidExperimentException(
                message: sprintf(
                    'Fallback variant "%s" does not exist in experiment "%s"',
                    $fallbackVariant,
                    $name,
                ),
            );
        }

        $totalWeight = array_sum($variants);

        if ($totalWeight <= 0) {
            throw new Exception\InvalidExperimentException(
                message: sprintf('Total weight must be greater than 0 in experiment "%s"', $name),
            );
        }

        $this->name = $name;
        $this->salt = $salt;
        $this->fallbackVariant = $fallbackVariant;
        $this->variants = $variants;
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
