<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Writes each conversion as one structured PSR-3 record whose context is the
 * canonical schema v2 row. The conversion half of the log-shipping delivery
 * path — see {@see LoggerExposureTracker} for how it is collected.
 *
 * Core `config/di.php` does not bind it (one-source rule): the application binds
 * `ConversionTracker => LoggerConversionTracker` in its own root-layer config.
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
        private EventSerializer $serializer = new CanonicalEventSerializer(),
    ) {}

    #[\Override]
    public function trackConversion(ConversionEvent $event): void
    {
        $this->logger->log(
            $this->level,
            'A/B test conversion',
            ['event' => $this->serializer->conversion($event)],
        );
    }
}
