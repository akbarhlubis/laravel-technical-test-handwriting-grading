<?php

namespace Tests\Unit;

use App\Services\Gemini\GradingTemporarilyUnavailableException;
use App\Services\Gemini\HandwritingGradingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-3.5-flash',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);
    }

    public function test_temporary_failure_then_success_uses_two_attempts(): void
    {
        $this->fakeSequence([503, $this->successResponse()]);
        $delays = [];

        $result = $this->service()->observe(['操场'], 'image-bytes', 'image/jpeg', function (int $milliseconds) use (&$delays): void {
            $delays[] = $milliseconds;
        }, fn (): float => 0.25);

        self::assertSame(['results' => [['characterName' => '操场', 'recognizedText' => null, 'isCorrect' => true]]], $result);
        self::assertSame([1025], $delays);
        self::assertSame(2, $this->requestCount());
    }

    public function test_two_temporary_failures_then_success_uses_three_attempts(): void
    {
        $this->fakeSequence([['error' => ['status' => 'UNAVAILABLE']], ['error' => ['code' => 'UNAVAILABLE']], $this->successResponse()]);
        $delays = [];

        $this->service()->observe(['操场'], 'image-bytes', 'image/jpeg', function (int $milliseconds) use (&$delays): void {
            $delays[] = $milliseconds;
        }, fn (): float => 0.5);

        self::assertSame([1050, 2050], $delays);
        self::assertSame(3, $this->requestCount());
    }

    public function test_three_temporary_failures_throw_safe_exception_after_three_attempts(): void
    {
        $this->fakeSequence([503, 503, 503]);
        $attempts = 0;

        $this->expectException(GradingTemporarilyUnavailableException::class);
        try {
            $this->service()->observe(['操场'], 'image-bytes', 'image/jpeg', function () use (&$attempts): void {
                $attempts++;
            }, fn (): float => 0);
        } finally {
            self::assertSame(2, $attempts);
            self::assertSame(3, $this->requestCount());
        }
    }

    public function test_non_retryable_responses_make_one_request(): void
    {
        $this->fakeSequence([400]);
        $this->expectExceptionMessage('Unable to observe handwriting.');

        try {
            $this->service()->observe(['操场'], 'image-bytes', 'image/jpeg', function (): void {
                self::fail('Non-retryable response must not sleep.');
            });
        } finally {
            self::assertSame(1, $this->requestCount());
        }
    }

    private function service(): HandwritingGradingService
    {
        return app(HandwritingGradingService::class);
    }

    private function fakeSequence(array $responses): void
    {
        Http::fake(function (Request $request) use (&$responses) {
            $response = array_shift($responses);

            if (is_array($response) && array_key_exists('error', $response)) {
                return Http::response($response, 503);
            }

            if (is_int($response)) {
                return Http::response([], $response);
            }

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => json_encode($response, JSON_UNESCAPED_UNICODE)]],
                    ],
                ]],
            ], 200);
        });
    }

    private function successResponse(): array
    {
        return ['results' => [['characterName' => '操场', 'recognizedText' => null, 'isCorrect' => true]]];
    }

    private function requestCount(): int
    {
        return count(Http::recorded());
    }
}
