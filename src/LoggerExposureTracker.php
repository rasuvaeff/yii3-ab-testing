<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Writes each exposure as one structured PSR-3 log record.
 *
 * Default zero-infra sink. Core `config/di.php` does not bind it (one-source
 * rule): the application binds `ExposureTracker => LoggerExposureTracker` in its
 * own root-layer config.
 *
 * @api
 */
final readonly class LoggerExposureTracker implements ExposureTracker
{
    /**
     * @param LogLevel::* $level
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $level = LogLevel::INFO,
    ) {}

    #[\Override]
    public function trackExposure(Assignment $assignment): void
    {
        $this->logger->log(
            $this->level,
            'A/B test exposure',
            [
                'experiment' => $assignment->experiment,
                'variant' => $assignment->variant,
                'subjectId' => $assignment->subjectId,
                'isForced' => $assignment->isForced,
                'isFallback' => $assignment->isFallback,
                'isSticky' => $assignment->isSticky,
                'environment' => $assignment->context?->getEnvironment(),
                'attributes' => $assignment->context?->getAttributes() ?? [],
            ],
        );
    }
}
