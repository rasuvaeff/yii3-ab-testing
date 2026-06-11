<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Writes each conversion as one structured PSR-3 log record.
 *
 * Default zero-infra sink. Core `config/di.php` does not bind it (one-source
 * rule): the application binds `ConversionTracker => LoggerConversionTracker` in
 * its own root-layer config.
 *
 * @api
 */
final readonly class LoggerConversionTracker implements ConversionTracker
{
    /**
     * @param LogLevel::* $level
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $level = LogLevel::INFO,
    ) {}

    #[\Override]
    public function trackConversion(Assignment $assignment, string $goal): void
    {
        $this->logger->log(
            $this->level,
            'A/B test conversion',
            [
                'experiment' => $assignment->experiment,
                'variant' => $assignment->variant,
                'subjectId' => $assignment->subjectId,
                'goal' => $goal,
                'isForced' => $assignment->isForced,
                'isFallback' => $assignment->isFallback,
                'isSticky' => $assignment->isSticky,
                'environment' => $assignment->context?->getEnvironment(),
                'attributes' => $assignment->context?->getAttributes() ?? [],
            ],
        );
    }
}
