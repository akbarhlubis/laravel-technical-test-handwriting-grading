<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeminiObservationTest extends TestCase
{
    private string $lessonId = '865dc30c-a821-4589-b088-a4a96d883541';

    private string $submissionId = '1c2b4204-7908-4ca5-b74d-1679df1dcb7a';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.supabase' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
            'services.supabase.url' => 'https://example.supabase.co',
            'services.supabase.secret_key' => 'test-storage-secret',
            'services.supabase.storage_bucket' => 'handwriting-submissions',
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-3.5-flash',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        DB::purge('supabase');
        Schema::connection('supabase')->create('lessons', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('moe_level');
            $table->text('word_list');
        });
        Schema::connection('supabase')->create('submissions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('lesson_id');
            $table->string('student_id')->nullable();
            $table->string('image_path');
            $table->integer('score')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::connection('supabase')->create('character_results', function (Blueprint $table): void {
            $table->id();
            $table->string('submission_id');
        });

        DB::connection('supabase')->table('lessons')->insert([
            'id' => $this->lessonId,
            'title' => '第十课 - 我们的校园',
            'moe_level' => 'P2',
            'word_list' => '{"操场","礼堂","老师"}',
        ]);
        DB::connection('supabase')->table('submissions')->insert([
            'id' => $this->submissionId,
            'lesson_id' => $this->lessonId,
            'student_id' => null,
            'image_path' => $this->lessonId.'/image.jpg',
            'score' => null,
            'created_at' => now(),
        ]);
    }

    public function test_preview_retrieves_private_image_and_returns_raw_structured_observation(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'results' => [
                                    ['characterName' => '操场', 'recognizedText' => null, 'isCorrect' => false],
                                    ['characterName' => '礼堂', 'recognizedText' => '礼堂', 'isCorrect' => true],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ], 200);
        });

        $response = $this->postJson('/submissions/'.$this->submissionId.'/grade-preview');

        $response->assertOk()
            ->assertJsonPath('submission_id', $this->submissionId)
            ->assertJsonPath('expected_words.0', '操场')
            ->assertJsonPath('observation.results.0.characterName', '操场')
            ->assertJsonPath('observation.results.0.recognizedText', null)
            ->assertJsonPath('observation.results.1.isCorrect', true)
            ->assertJsonPath('normalized_results.0.characterName', '操场')
            ->assertJsonPath('normalized_results.1.characterName', '礼堂')
            ->assertJsonPath('normalized_results.2.recognizedText', null)
            ->assertDontSee('test-gemini-key');

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/models/gemini-3.5-flash:generateContent')) {
                return false;
            }

            $body = $request->data();

            return $body['contents'][0]['parts'][0]['inlineData']['mimeType'] === 'image/jpeg'
                && base64_decode($body['contents'][0]['parts'][0]['inlineData']['data'], true) === 'jpeg-bytes'
                && str_contains($body['contents'][0]['parts'][1]['text'], '操场')
                && $body['generationConfig']['response_mime_type'] === 'application/json'
                && $body['generationConfig']['response_schema']['properties']['results']['type'] === 'ARRAY';
        });

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_ends_with($request->url(), '/storage/v1/object/handwriting-submissions/'.$this->lessonId.'/image.jpg');
        });

        self::assertNull(DB::connection('supabase')->table('submissions')->value('score'));
        self::assertSame(0, DB::connection('supabase')->table('character_results')->count());
    }

    public function test_malformed_gemini_output_returns_safe_error(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"results":[{"isCorrect":"yes"}]}']]],
                ]],
            ], 200);
        });

        $response = $this->postJson('/submissions/'.$this->submissionId.'/grade-preview');

        $response->assertStatus(502)
            ->assertExactJson(['message' => 'Unable to preview handwriting grading.'])
            ->assertDontSee('test-gemini-key');
    }
}
