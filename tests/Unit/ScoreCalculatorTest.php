<?php

namespace Tests\Unit;

use App\Services\Grading\ScoreCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    #[DataProvider('scoreProvider')]
    public function test_score_matches_the_reference_formula(array $results, int $expected): void
    {
        $score = (new ScoreCalculator)->calculate($results);

        self::assertSame($expected, $score);
        self::assertIsInt($score);
    }

    public static function scoreProvider(): array
    {
        return [
            'all correct' => [[['isCorrect' => true], ['isCorrect' => true]], 100],
            'all incorrect' => [[['isCorrect' => false], ['isCorrect' => false]], 0],
            'two of three' => [[['isCorrect' => true], ['isCorrect' => false], ['isCorrect' => true]], 67],
            'one of three' => [[['isCorrect' => true], ['isCorrect' => false], ['isCorrect' => false]], 33],
            'one of two' => [[['isCorrect' => true], ['isCorrect' => false]], 50],
            'empty' => [[], 0],
            'strict boolean true only' => [[['isCorrect' => 1], ['isCorrect' => 'true'], ['isCorrect' => true]], 33],
        ];
    }
}
