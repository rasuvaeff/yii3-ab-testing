<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use DateTimeImmutable;
use DateTimeZone;
use Rasuvaeff\Yii3AbTesting\Internal\EventFields;

/**
 * A subject completed a goal. Carries the same decision fields as
 * {@see ExposureEvent} so a report can group both without a join back to the
 * experiment definition.
 *
 * `exposureEventId` is optional: with it the conversion is attributed to a
 * specific exposure, without it attribution falls back to the configured
 * {@see AttributionWindow} around the subject's first exposure.
 *
 * @api
 */
final readonly class ConversionEvent
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

    /** @var non-empty-string */
    public string $goal;

    /** @var non-empty-string|null */
    public ?string $experimentRevision;

    /** @var non-empty-string|null */
    public ?string $exposureEventId;

    /** @var array<non-empty-string, scalar> */
    public array $dimensions;

    /**
     * @param array<string, scalar> $dimensions
     */
    public function __construct(
        string $eventId,
        DateTimeImmutable $occurredAt,
        string $experiment,
        string $variant,
        string $subjectId,
        string $goal,
        public DecisionReason $reason,
        public AssignmentSource $source,
        ?string $experimentRevision = null,
        public string $environment = '',
        array $dimensions = [],
        ?string $exposureEventId = null,
    ) {
        $this->eventId = EventFields::requireNonEmpty($eventId, 'eventId');
        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
        $this->experiment = EventFields::requireNonEmpty($experiment, 'experiment');
        $this->variant = EventFields::requireNonEmpty($variant, 'variant');
        $this->subjectId = EventFields::requireNonEmpty($subjectId, 'subjectId');
        $this->goal = EventFields::requireNonEmpty($goal, 'goal');
        $this->experimentRevision = EventFields::requireNullOrNonEmpty($experimentRevision, 'experimentRevision');
        $this->exposureEventId = EventFields::requireNullOrNonEmpty($exposureEventId, 'exposureEventId');
        $this->dimensions = EventFields::requireDimensions($dimensions);
    }

    public function isAnalyzable(): bool
    {
        return $this->reason->isAnalyzable();
    }
}
