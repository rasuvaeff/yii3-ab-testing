<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Rasuvaeff\Yii3AbTesting\Internal\EventFields;

/**
 * Proof of an exposure, small enough to travel in a cookie or session so a
 * conversion in a later request can be attributed without resolving the
 * assignment again (which could return a different variant after a reweight).
 *
 * Deliberately carries no `environment` or `dimensions`: receipts are stored in
 * size-capped cookies, and a conversion records the context of its own request
 * anyway. The link back to the exposure is `exposureEventId`.
 *
 * @api
 */
final readonly class AssignmentReceipt
{
    private const string DATETIME_FORMAT = 'Y-m-d\TH:i:s.vp';

    /** @var non-empty-string */
    public string $exposureEventId;

    public DateTimeImmutable $occurredAt;

    /** @var non-empty-string */
    public string $experiment;

    /** @var non-empty-string */
    public string $variant;

    /** @var non-empty-string */
    public string $subjectId;

    /** @var non-empty-string|null */
    public ?string $experimentRevision;

    public function __construct(
        string $exposureEventId,
        DateTimeImmutable $occurredAt,
        string $experiment,
        string $variant,
        string $subjectId,
        public DecisionReason $reason,
        public AssignmentSource $source,
        ?string $experimentRevision = null,
    ) {
        $this->exposureEventId = EventFields::requireNonEmpty($exposureEventId, 'exposureEventId');
        $this->occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
        $this->experiment = EventFields::requireNonEmpty($experiment, 'experiment');
        $this->variant = EventFields::requireNonEmpty($variant, 'variant');
        $this->subjectId = EventFields::requireNonEmpty($subjectId, 'subjectId');
        $this->experimentRevision = EventFields::requireNullOrNonEmpty($experimentRevision, 'experimentRevision');
    }

    /**
     * @return array{
     *     eid: non-empty-string,
     *     at: non-empty-string,
     *     e: non-empty-string,
     *     v: non-empty-string,
     *     s: non-empty-string,
     *     r: non-empty-string,
     *     src: non-empty-string,
     *     rev: non-empty-string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'eid' => $this->exposureEventId,
            'at' => $this->occurredAt->format(self::DATETIME_FORMAT),
            'e' => $this->experiment,
            'v' => $this->variant,
            's' => $this->subjectId,
            'r' => $this->reason->value,
            'src' => $this->source->value,
            'rev' => $this->experimentRevision,
        ];
    }

    /**
     * Rebuilds a receipt from {@see toArray()} output.
     *
     * The input is transport data — a cookie value is attacker-controlled even
     * when signed — so every field is re-validated rather than trusted.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException When a field is missing, of the wrong
     *     type, or carries a value outside the known enums.
     */
    public static function fromArray(array $data): self
    {
        $occurredAt = DateTimeImmutable::createFromFormat(
            self::DATETIME_FORMAT,
            self::readString($data, 'at'),
        );

        if ($occurredAt === false) {
            throw new InvalidArgumentException('Receipt field "at" is not a valid timestamp');
        }

        $reason = DecisionReason::tryFrom(self::readString($data, 'r'));

        if ($reason === null) {
            throw new InvalidArgumentException('Receipt field "r" is not a known decision reason');
        }

        $source = AssignmentSource::tryFrom(self::readString($data, 'src'));

        if ($source === null) {
            throw new InvalidArgumentException('Receipt field "src" is not a known assignment source');
        }

        $revision = $data['rev'] ?? null;

        if ($revision !== null && !\is_string($revision)) {
            throw new InvalidArgumentException('Receipt field "rev" must be a string or null');
        }

        return new self(
            exposureEventId: self::readString($data, 'eid'),
            occurredAt: $occurredAt,
            experiment: self::readString($data, 'e'),
            variant: self::readString($data, 'v'),
            subjectId: self::readString($data, 's'),
            reason: $reason,
            source: $source,
            experimentRevision: $revision,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!\is_string($value)) {
            throw new InvalidArgumentException(sprintf('Receipt field "%s" must be a string', $key));
        }

        return $value;
    }
}
