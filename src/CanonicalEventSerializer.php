<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * The canonical schema v2 serializer.
 *
 * Output is byte-reproducible for a given event: dimension keys are sorted, and
 * absent optional values become the empty string rather than null so that every
 * row has the same shape as the analytics table's column defaults.
 *
 * @psalm-import-type AbExposureRow from EventSerializer
 * @psalm-import-type AbConversionRow from EventSerializer
 *
 * @api
 */
final readonly class CanonicalEventSerializer implements EventSerializer
{
    /** Schema generation carried in every row so consumers can branch on it. */
    public const int SCHEMA_VERSION = 2;

    /** Millisecond precision, matching `DateTime64(3, 'UTC')` in ClickHouse. */
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s.v';

    /**
     * @return AbExposureRow
     */
    #[\Override]
    public function exposure(ExposureEvent $event): array
    {
        return [
            'v' => self::SCHEMA_VERSION,
            'event_id' => $event->eventId,
            'occurred_at' => $this->formatTime($event->occurredAt),
            'experiment' => $event->experiment,
            'variant' => $event->variant,
            'subject_id' => $event->subjectId,
            'decision_reason' => $event->reason->value,
            'assignment_source' => $event->source->value,
            'experiment_revision' => $event->experimentRevision ?? '',
            'environment' => $event->environment,
            'dimensions' => $this->encodeDimensions($event->dimensions),
        ];
    }

    /**
     * @return AbConversionRow
     */
    #[\Override]
    public function conversion(ConversionEvent $event): array
    {
        return [
            'v' => self::SCHEMA_VERSION,
            'event_id' => $event->eventId,
            'occurred_at' => $this->formatTime($event->occurredAt),
            'experiment' => $event->experiment,
            'variant' => $event->variant,
            'subject_id' => $event->subjectId,
            'goal' => $event->goal,
            'decision_reason' => $event->reason->value,
            'assignment_source' => $event->source->value,
            'experiment_revision' => $event->experimentRevision ?? '',
            'environment' => $event->environment,
            'dimensions' => $this->encodeDimensions($event->dimensions),
            'exposure_event_id' => $event->exposureEventId ?? '',
        ];
    }

    /**
     * @return non-empty-string
     */
    private function formatTime(\DateTimeImmutable $time): string
    {
        /** @var non-empty-string */
        return $time->format(self::DATETIME_FORMAT);
    }

    /**
     * @param array<non-empty-string, scalar> $dimensions
     *
     * @return non-empty-string JSON object; `{}` when there are no dimensions,
     *     never an empty string, so the column default and a written value
     *     cannot be told apart by accident.
     */
    private function encodeDimensions(array $dimensions): string
    {
        ksort($dimensions);

        /** @var non-empty-string */
        return json_encode($dimensions, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
