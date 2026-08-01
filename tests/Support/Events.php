<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Rasuvaeff\Yii3AbTesting\AssignmentSource;
use Rasuvaeff\Yii3AbTesting\ConversionEvent;
use Rasuvaeff\Yii3AbTesting\DecisionReason;
use Rasuvaeff\Yii3AbTesting\ExposureEvent;

/**
 * Builders for events with sensible defaults, so a test only states the field
 * it is actually about.
 */
final class Events
{
    public const string OCCURRED_AT = '2026-08-01 10:00:00.123';

    /**
     * @param array<string, scalar> $dimensions
     */
    public static function exposure(
        string $eventId = 'evt-1',
        string $experiment = 'checkout_button',
        string $variant = 'b',
        string $subjectId = 'subject-1',
        DecisionReason $reason = DecisionReason::Assigned,
        AssignmentSource $source = AssignmentSource::Computed,
        ?string $experimentRevision = null,
        string $environment = '',
        array $dimensions = [],
        ?string $occurredAt = null,
    ): ExposureEvent {
        return new ExposureEvent(
            eventId: $eventId,
            occurredAt: self::time($occurredAt),
            experiment: $experiment,
            variant: $variant,
            subjectId: $subjectId,
            reason: $reason,
            source: $source,
            experimentRevision: $experimentRevision,
            environment: $environment,
            dimensions: $dimensions,
        );
    }

    /**
     * @param array<string, scalar> $dimensions
     */
    public static function conversion(
        string $eventId = 'evt-2',
        string $experiment = 'checkout_button',
        string $variant = 'b',
        string $subjectId = 'subject-1',
        string $goal = 'purchase',
        DecisionReason $reason = DecisionReason::Assigned,
        AssignmentSource $source = AssignmentSource::Computed,
        ?string $experimentRevision = null,
        string $environment = '',
        array $dimensions = [],
        ?string $exposureEventId = null,
        ?string $occurredAt = null,
    ): ConversionEvent {
        return new ConversionEvent(
            eventId: $eventId,
            occurredAt: self::time($occurredAt),
            experiment: $experiment,
            variant: $variant,
            subjectId: $subjectId,
            goal: $goal,
            reason: $reason,
            source: $source,
            experimentRevision: $experimentRevision,
            environment: $environment,
            dimensions: $dimensions,
            exposureEventId: $exposureEventId,
        );
    }

    public static function time(?string $value = null): DateTimeImmutable
    {
        return new DateTimeImmutable($value ?? self::OCCURRED_AT, new DateTimeZone('UTC'));
    }
}
