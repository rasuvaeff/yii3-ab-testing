<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use DateTimeImmutable;
use DateTimeZone;
use Rasuvaeff\Yii3AbTesting\Internal\EventFields;

/**
 * A subject was shown a variant. This is the analytics event contract v2 —
 * everything a report needs, and nothing storage-specific.
 *
 * `occurredAt` is the moment the exposure was tracked, never the moment a
 * worker later delivered it: deduplication and time partitioning both depend on
 * it staying stable across retries. It is normalised to UTC on construction so
 * every serializer produces the same bytes for the same event.
 *
 * @api
 */
final readonly class ExposureEvent
{
    /** @var non-empty-string */
    public string $eventId;

    public DateTimeImmutable $occurredAt;

    /** @var non-empty-string */
    public string $experiment;

    /** @var non-empty-string */
    public string $variant;

    /** @var non-empty-string */
    public string $subjectId;

    /** @var non-empty-string|null */
    public ?string $experimentRevision;

    /** @var array<non-empty-string, scalar> */
    public array $dimensions;

    /**
     * @param array<string, scalar> $dimensions Already filtered by an
     *     {@see AnalyticsContextPolicy}; the event does not filter again.
     * @param string $environment Empty string means "no context", which is a
     *     legitimate value rather than a missing one.
     */
    public function __construct(
        string $eventId,
        DateTimeImmutable $occurredAt,
        string $experiment,
        string $variant,
        string $subjectId,
        public DecisionReason $reason,
        public AssignmentSource $source,
        ?string $experimentRevision = null,
        public string $environment = '',
        array $dimensions = [],
    ) {
        $this->eventId = EventFields::requireNonEmpty($eventId, 'eventId');
        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
        $this->experiment = EventFields::requireNonEmpty($experiment, 'experiment');
        $this->variant = EventFields::requireNonEmpty($variant, 'variant');
        $this->subjectId = EventFields::requireNonEmpty($subjectId, 'subjectId');
        $this->experimentRevision = EventFields::requireNullOrNonEmpty($experimentRevision, 'experimentRevision');
        $this->dimensions = EventFields::requireDimensions($dimensions);
    }

    /**
     * Whether this exposure counts as experiment participation. Forced and
     * fallback traffic is excluded from analysis regardless of its source.
     */
    public function isAnalyzable(): bool
    {
        return $this->reason->isAnalyzable();
    }

    /**
     * Receipt for attributing a conversion that happens in a later request.
     */
    public function receipt(): AssignmentReceipt
    {
        return new AssignmentReceipt(
            exposureEventId: $this->eventId,
            occurredAt: $this->occurredAt,
            experiment: $this->experiment,
            variant: $this->variant,
            subjectId: $this->subjectId,
            reason: $this->reason,
            source: $this->source,
            experimentRevision: $this->experimentRevision,
        );
    }
}
