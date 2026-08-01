<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Suppresses repeated exposures within its lifetime. Bind it request-scoped or
 * call {@see reset()} at the request boundary in a long-running worker.
 *
 * @api
 */
final class DeduplicatingExposureTracker implements ExposureTracker, FlushableTracker
{
    /** @var array<string, true> */
    private array $tracked = [];

    public function __construct(
        private readonly ExposureTracker $tracker,
    ) {}

    #[\Override]
    public function trackExposure(ExposureEvent $event): void
    {
        $key = hash(
            algo: 'sha256',
            data: implode("\0", [
                $event->experiment,
                $event->subjectId,
                $event->experimentRevision ?? '',
            ]),
        );

        if ($this->tracked[$key] ?? false) {
            return;
        }

        $this->tracker->trackExposure($event);
        $this->tracked[$key] = true;
    }

    public function reset(): void
    {
        $this->tracked = [];
    }

    #[\Override]
    public function flush(): void
    {
        if ($this->tracker instanceof FlushableTracker) {
            $this->tracker->flush();
        }
    }
}
