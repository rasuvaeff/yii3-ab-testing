<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting\Internal;

use InvalidArgumentException;

/**
 * Shared field validation for the event DTOs. Both event kinds carry the same
 * ten identity/decision fields, so the rules live here rather than being
 * duplicated (and drifting) between them.
 *
 * @internal
 */
final class EventFields
{
    /**
     * @return non-empty-string
     */
    public static function requireNonEmpty(string $value, string $field): string
    {
        if ($value === '' || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Event field "%s" must not be empty', $field));
        }

        return $value;
    }

    /**
     * A null value means "not known"; an empty string never does.
     *
     * @return non-empty-string|null
     */
    public static function requireNullOrNonEmpty(?string $value, string $field): ?string
    {
        return $value === null ? null : self::requireNonEmpty($value, $field);
    }

    /**
     * Dimension names address a column in analytics, so an empty name is a bug
     * that would otherwise surface as an unqueryable row.
     *
     * @param array<string, scalar> $dimensions
     *
     * @return array<non-empty-string, scalar>
     */
    public static function requireDimensions(array $dimensions): array
    {
        $validated = [];

        foreach ($dimensions as $name => $value) {
            $validated[self::requireNonEmpty($name, 'dimensions')] = $value;
        }

        return $validated;
    }
}
