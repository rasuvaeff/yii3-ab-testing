<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Contract for trackers that buffer events in memory and write them out in
 * batches. Call {@see flush()} once at request end (PSR-15 terminate /
 * shutdown). The composite trackers implement it by propagating the flush to
 * every flushable inner tracker, so an application bound to the tracker
 * interfaces can flush without knowing the concrete sink.
 *
 * @api
 */
interface FlushableTracker
{
    public function flush(): void;
}
