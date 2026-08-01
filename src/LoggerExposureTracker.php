<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Writes each exposure as one structured PSR-3 record whose context is the
 * canonical schema v2 row.
 *
 * This is the **log-shipping delivery path**: a collector (Vector, Fluent Bit,
 * Kafka) picks the record up and loads it into the same analytics tables the
 * durable outbox path writes. Nothing in the request touches the network, and a
 * dying worker cannot lose a buffer because there is no buffer.
 *
 * The row is emitted under a single `event` key rather than spread across the
 * record, so a collector can map it to columns without having to know which
 * fields belong to the event and which the logger added.
 *
 * Core `config/di.php` does not bind it (one-source rule): the application binds
 * `ExposureTracker => LoggerExposureTracker` in its own root-layer config.
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
        private EventSerializer $serializer = new CanonicalEventSerializer(),
    ) {}

    #[\Override]
    public function trackExposure(ExposureEvent $event): void
    {
        $this->logger->log(
            $this->level,
            'A/B test exposure',
            ['event' => $this->serializer->exposure($event)],
        );
    }
}
