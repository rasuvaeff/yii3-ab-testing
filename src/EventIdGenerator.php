<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Mints the identity of an analytics event.
 *
 * The identifier is the deduplication key of the whole pipeline: a delivery
 * retried after an uncertain outcome must carry the same value, so that the
 * storage engine can collapse the duplicate. It is therefore minted once, when
 * the event is tracked, and never regenerated downstream.
 *
 * The format is not part of the contract. Implementations return an opaque
 * string, and the analytics column is a string rather than a UUID type, so
 * ULIDs, snowflakes or an application's own keys are all valid. UUIDv7 is only
 * the default, chosen because it sorts by time.
 *
 * @api
 */
interface EventIdGenerator
{
    /**
     * @return non-empty-string
     */
    public function generate(): string;
}
