<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3AbTesting;

/**
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
