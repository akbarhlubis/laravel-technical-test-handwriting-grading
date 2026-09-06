<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class HandwritingGradingService
{
    public function observe(array $expectedWords, string $imageBytes, string $mimeType): array
    {
        if ($expectedWords === []) {
            return ['results' => []];
        }

        $apiKey = trim((string) config('services.gemini.api_key'));
        $model = trim((string) config('services.gemini.model', 'gemini-3.5-flash'));
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');

        if ($apiKey === '' || $model === '' || $baseUrl === '') {
            Log::error('Gemini observation is not configured.', ['model' => $model]);

            throw new GeminiObservationException(status: 503);
        }

        $payload = [
            'contents' => [[
                'parts' => [
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data' => base64_encode($imageBytes),
                        ],
                    ],
                    [
                        'text' => "Grade the Chinese handwriting in this image.\n"
                            .'The expected words, in order, are: '.json_encode($expectedWords, JSON_UNESCAPED_UNICODE).".\n"
                            .'Return one raw observation for every visible handwritten word. '
                            .'Do not invent words. isCorrect must be true only when the handwritten word matches the expected word. '
                            .'Return JSON only and do not calculate a score or explain the result.',
                    ],
                ],
            ]],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'results' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'characterName' => ['type' => 'STRING'],
                                    'recognizedText' => ['type' => 'STRING', 'nullable' => true],
                                    'isCorrect' => ['type' => 'BOOLEAN'],
                                ],
                                'required' => ['characterName', 'recognizedText', 'isCorrect'],
                            ],
                        ],
                    ],
                    'required' => ['results'],
                ],
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post("{$baseUrl}/models/".rawurlencode($model).':generateContent', $payload);
        } catch (ConnectionException $exception) {
            Log::error('Gemini observation request failed.', [
                'model' => $model,
                'exception' => get_class($exception),
            ]);

            throw new GeminiObservationException(status: 503, previous: $exception);
        }

        if (! $response->successful()) {
            Log::error('Gemini observation was rejected.', [
                'model' => $model,
                'status' => $response->status(),
            ]);

            throw new GeminiObservationException(status: $response->serverError() ? 503 : 502);
        }

        $text = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn ($part): bool => is_string($part) && $part !== '')
            ->first();

        if (! is_string($text)) {
            throw new GeminiObservationException;
        }

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GeminiObservationException(previous: $exception);
        }

        if (! is_array($decoded) || ! isset($decoded['results']) || ! is_array($decoded['results'])) {
            throw new GeminiObservationException;
        }

        foreach ($decoded['results'] as $result) {
            if (! is_array($result)
                || ! array_key_exists('characterName', $result)
                || ! is_string($result['characterName'])
                || ! array_key_exists('recognizedText', $result)
                || (! is_null($result['recognizedText']) && ! is_string($result['recognizedText']))
                || ! array_key_exists('isCorrect', $result)
                || ! is_bool($result['isCorrect'])) {
                throw new GeminiObservationException;
            }
        }

        return ['results' => $decoded['results']];
    }
}
