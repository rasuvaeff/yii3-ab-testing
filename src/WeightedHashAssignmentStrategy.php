<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
 * Deterministic weighted assignment: `sha256(salt . ':' . subjectId)`, first
 * 8 hex digits → 32-bit bucket, modulo total weight. Requires 64-bit PHP — the
 * bucket value exceeds `PHP_INT_MAX` on 32-bit builds.
 *
 * @api
 */
final readonly class WeightedHashAssignmentStrategy implements AssignmentStrategy
{
    #[\Override]
    public function assign(string $salt, string $subjectId, array $variants): string
    {
        $sorted = $variants;
        ksort($sorted);

        $totalWeight = array_sum($sorted);

        if ($totalWeight <= 0) {
            throw new \InvalidArgumentException('Total variant weight must be greater than 0');
        }

        $digest = hash('sha256', $salt . ':' . $subjectId);
        $bucket = hexdec(substr($digest, 0, 8)) % $totalWeight;

        $cumulative = 0;

        foreach ($sorted as $name => $weight) {
            $cumulative += $weight;

            if ($bucket < $cumulative) {
                return $name;
            }
        }

        /** @var string $last */
        $last = array_key_last($sorted);

        return $last;
    }
}
