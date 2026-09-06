<?php

namespace Tests\Unit;

use App\Services\Grading\GradingNormalizationException;
use App\Services\Grading\GradingResultNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GradingResultNormalizerTest extends TestCase
{
    private GradingResultNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new GradingResultNormalizer;
    }

    public function test_normalized_results_follow_authoritative_order_and_trim_values(): void
    {
        $results = $this->normalizer->normalize([
            ['characterName' => ' 老师 ', 'recognizedText' => ' 老师 ', 'isCorrect' => true],
            ['characterName' => '操场', 'recognizedText' => ' 操场 ', 'isCorrect' => true],
        ], [' 操场 ', '礼堂', '老师']);

        self::assertSame([
            ['characterName' => '操场', 'recognizedText' => '操场', 'isCorrect' => true],
            ['characterName' => '礼堂', 'recognizedText' => null, 'isCorrect' => false],
            ['characterName' => '老师', 'recognizedText' => '老师', 'isCorrect' => true],
        ], $results);
    }

    public function test_unexpected_words_are_ignored(): void
    {
        $results = $this->normalizer->normalize([
            ['characterName' => '学校', 'recognizedText' => '学校', 'isCorrect' => true],
            ['characterName' => '操场', 'recognizedText' => null, 'isCorrect' => false],
        ], ['操场', '礼堂']);

        self::assertCount(2, $results);
        self::assertSame('操场', $results[0]['characterName']);
        self::assertSame('礼堂', $results[1]['characterName']);
    }

    public function test_missing_words_use_the_conservative_fallback(): void
    {
        $results = $this->normalizer->normalize([
            ['characterName' => '操场', 'recognizedText' => '操场', 'isCorrect' => true],
        ], ['操场', '礼堂']);

        self::assertSame(['characterName' => '礼堂', 'recognizedText' => null, 'isCorrect' => false], $results[1]);
    }

    public function test_empty_recognized_text_and_missing_recognized_text_become_null(): void
    {
        $results = $this->normalizer->normalize([
            ['characterName' => '操场', 'recognizedText' => '   ', 'isCorrect' => false],
            ['characterName' => '礼堂', 'isCorrect' => false],
        ], ['操场', '礼堂']);

        self::assertNull($results[0]['recognizedText']);
        self::assertNull($results[1]['recognizedText']);
    }

    public function test_empty_expected_words_return_empty_results(): void
    {
        self::assertSame([], $this->normalizer->normalize([], []));
    }

    #[DataProvider('invalidInputProvider')]
    public function test_invalid_authoritative_or_observation_input_is_rejected(array $observations, array $expected): void
    {
        $this->expectException(GradingNormalizationException::class);
        $this->normalizer->normalize($observations, $expected);
    }

    public static function invalidInputProvider(): array
    {
        return [
            'empty expected word' => [[], ['']],
            'duplicate expected words' => [[], ['操场', '操场']],
            'duplicate observation' => [[
                ['characterName' => '操场', 'isCorrect' => true],
                ['characterName' => '操场', 'isCorrect' => true],
            ], ['操场']],
            'invalid character name' => [[['characterName' => 123, 'isCorrect' => true]], ['操场']],
            'invalid correctness' => [[['characterName' => '操场', 'isCorrect' => 'true']], ['操场']],
            'invalid recognized text' => [[['characterName' => '操场', 'recognizedText' => 123, 'isCorrect' => true]], ['操场']],
        ];
    }
}
