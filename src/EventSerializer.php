<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Turns an event into the canonical flat row of analytics schema v2.
 *
 * This is the single source of the wire format. Every delivery path uses it —
 * the outbox payload, the log-shipping sink, the golden fixture — because the
 * defect this contract exists to close was two paths serializing the same
 * domain event differently and silently dropping different fields.
 *
 * Every value is a scalar or null by design: the ClickHouse outbox exporter
 * rejects nested payload fields, so `dimensions` travels as a JSON string
 * rather than a map.
 *
 * **The row shapes below are part of the public contract.** Adapters import
 * them with `@psalm-import-type`, so renaming or removing a key breaks their
 * static analysis — which is the point. `roave/backward-compatibility-check`
 * cannot see docblock aliases, so treat a change here exactly like a changed
 * signature: major only.
 *
 * @psalm-type AbExposureRow = array{
 *     v: 2,
 *     event_id: non-empty-string,
 *     occurred_at: non-empty-string,
 *     experiment: non-empty-string,
 *     variant: non-empty-string,
 *     subject_id: non-empty-string,
 *     decision_reason: non-empty-string,
 *     assignment_source: non-empty-string,
 *     experiment_revision: string,
 *     environment: string,
 *     dimensions: non-empty-string,
 * }
 * @psalm-type AbConversionRow = array{
 *     v: 2,
 *     event_id: non-empty-string,
 *     occurred_at: non-empty-string,
 *     experiment: non-empty-string,
 *     variant: non-empty-string,
 *     subject_id: non-empty-string,
 *     goal: non-empty-string,
 *     decision_reason: non-empty-string,
 *     assignment_source: non-empty-string,
 *     experiment_revision: string,
 *     environment: string,
 *     dimensions: non-empty-string,
 *     exposure_event_id: string,
 * }
 *
 * @api
 */
interface EventSerializer
{
    /**
     * @return AbExposureRow
     */
    public function exposure(ExposureEvent $event): array;

    /**
     * @return AbConversionRow
     */
    public function conversion(ConversionEvent $event): array;
}
