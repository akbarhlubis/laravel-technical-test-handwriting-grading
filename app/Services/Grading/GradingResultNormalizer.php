<?php

namespace App\Services\Grading;

class GradingResultNormalizer
{
    public function normalize(array $observationResults, array $expectedWords): array
    {
        $expected = [];

        foreach ($expectedWords as $word) {
            if (! is_string($word)) {
                throw new GradingNormalizationException('Lesson contains an invalid expected word.');
            }

            $expected[] = trim($word);
        }

        if (in_array('', $expected, true)) {
            throw new GradingNormalizationException('Lesson contains an empty expected word.');
        }

        if (count(array_unique($expected)) !== count($expected)) {
            throw new GradingNormalizationException('Lesson contains duplicate expected words.');
        }

        if ($expected === []) {
            return [];
        }

        $byWord = [];

        foreach ($observationResults as $item) {
            if (! is_array($item) || ! array_key_exists('characterName', $item) || ! is_string($item['characterName'])) {
                throw new GradingNormalizationException('Gemini returned an invalid character result.');
            }

            $characterName = trim($item['characterName']);

            if (! in_array($characterName, $expected, true)) {
                continue;
            }

            if (array_key_exists($characterName, $byWord) || ! array_key_exists('isCorrect', $item) || ! is_bool($item['isCorrect'])) {
                throw new GradingNormalizationException('Gemini returned duplicate or invalid character results.');
            }

            $recognizedText = $item['recognizedText'] ?? null;

            if ($recognizedText !== null && ! is_string($recognizedText)) {
                throw new GradingNormalizationException('Gemini returned an invalid recognized text value.');
            }

            $recognizedText = is_string($recognizedText) ? trim($recognizedText) : null;

            $byWord[$characterName] = [
                'characterName' => $characterName,
                'recognizedText' => $recognizedText !== '' ? $recognizedText : null,
                'isCorrect' => $item['isCorrect'],
            ];
        }

        return array_map(
            fn (string $word): array => $byWord[$word] ?? [
                'characterName' => $word,
                'recognizedText' => null,
                'isCorrect' => false,
            ],
            $expected,
        );
    }
}
