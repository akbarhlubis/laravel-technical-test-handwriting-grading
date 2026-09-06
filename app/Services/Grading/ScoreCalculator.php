<?php

namespace App\Services\Grading;

class ScoreCalculator
{
    public function calculate(array $normalizedResults): int
    {
        if ($normalizedResults === []) {
            return 0;
        }

        $correctCount = count(array_filter(
            $normalizedResults,
            fn ($result): bool => is_array($result) && ($result['isCorrect'] ?? null) === true,
        ));

        return (int) round(($correctCount / count($normalizedResults)) * 100);
    }
}
