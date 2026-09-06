<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GradingPersistenceTest extends TestCase
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
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('moe_level');
            $table->text('word_list');
        });
        Schema::connection('supabase')->create('submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('lesson_id');
            $table->string('image_path');
            $table->integer('score')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::connection('supabase')->create('character_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id');
            $table->text('character_name');
            $table->text('recognized_text')->nullable();
            $table->boolean('is_correct');
            $table->timestamp('created_at')->nullable();
        });

        DB::connection('supabase')->table('lessons')->insert([
            'id' => $this->lessonId,
            'title' => 'Lesson One',
            'moe_level' => 'P2',
            'word_list' => '{"操场","礼堂","老师"}',
        ]);
        DB::connection('supabase')->table('submissions')->insert([
            'id' => $this->submissionId,
            'lesson_id' => $this->lessonId,
            'image_path' => $this->lessonId.'/image.jpg',
            'score' => null,
            'created_at' => now(),
        ]);
    }

    public function test_grade_persists_normalized_results_and_score(): void
    {
        $this->fakeSuccessfulGemini();

        $response = $this->postJson('/submissions/'.$this->submissionId.'/grade');

        $response->assertCreated()
            ->assertJsonPath('submission_id', $this->submissionId)
            ->assertJsonPath('score', 67)
            ->assertJsonPath('normalized_results.1.recognizedText', null);

        $rows = DB::connection('supabase')->table('character_results')
            ->where('submission_id', $this->submissionId)
            ->orderBy('character_name')
            ->get();

        self::assertCount(3, $rows);
        self::assertSame('操场', $rows[0]->character_name);
        self::assertSame('操场', $rows[0]->recognized_text);
        self::assertTrue((bool) $rows[0]->is_correct);
        self::assertSame('礼堂', $rows[1]->character_name);
        self::assertNull($rows[1]->recognized_text);
        self::assertFalse((bool) $rows[1]->is_correct);
        self::assertSame('老师', $rows[2]->character_name);
        self::assertSame(67, DB::connection('supabase')->table('submissions')->where('id', $this->submissionId)->value('score'));
    }

    public function test_gemini_failure_does_not_persist_grading(): void
    {
        Http::fake(fn (Request $request) => $request->method() === 'GET'
            ? Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])
            : Http::response(['error' => 'unavailable'], 503));

        $response = $this->postJson('/submissions/'.$this->submissionId.'/grade');

        $response->assertStatus(503);
        self::assertNoGradingRows();
    }

    public function test_normalization_failure_does_not_persist_grading(): void
    {
        $this->fakeGeminiResult([
            ['characterName' => '操场', 'recognizedText' => null, 'isCorrect' => true],
            ['characterName' => '操场', 'recognizedText' => null, 'isCorrect' => true],
        ]);

        $response = $this->postJson('/submissions/'.$this->submissionId.'/grade');

        $response->assertStatus(422);
        self::assertNoGradingRows();
    }

    public function test_persistence_failure_rolls_back_rows_and_score(): void
    {
        $this->fakeSuccessfulGemini();
        DB::connection('supabase')->statement(
            "CREATE TRIGGER fail_score_update BEFORE UPDATE OF score ON submissions BEGIN SELECT RAISE(ABORT, 'score update failed'); END",
        );

        $response = $this->postJson('/submissions/'.$this->submissionId.'/grade');

        $response->assertStatus(500);
        self::assertNoGradingRows();
        self::assertNull(DB::connection('supabase')->table('submissions')->where('id', $this->submissionId)->value('score'));
    }

    public function test_repeated_grading_is_rejected_without_duplicates(): void
    {
        $this->fakeSuccessfulGemini();
        $this->postJson('/submissions/'.$this->submissionId.'/grade')->assertCreated();

        $response = $this->postJson('/submissions/'.$this->submissionId.'/grade');

        $response->assertStatus(409);
        self::assertSame(3, DB::connection('supabase')->table('character_results')->count());
    }

    private function fakeSuccessfulGemini(): void
    {
        $this->fakeGeminiResult([
            ['characterName' => '操场', 'recognizedText' => '操场', 'isCorrect' => true],
            ['characterName' => '礼堂', 'recognizedText' => null, 'isCorrect' => false],
            ['characterName' => '老师', 'recognizedText' => '老师', 'isCorrect' => true],
        ]);
    }

    private function fakeGeminiResult(array $results): void
    {
        Http::fake(function (Request $request) use ($results) {
            if ($request->method() === 'GET') {
                return Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['results' => $results], JSON_UNESCAPED_UNICODE)]]],
                ]],
            ], 200);
        });
    }

    private function assertNoGradingRows(): void
    {
        self::assertSame(0, DB::connection('supabase')->table('character_results')->count());
        self::assertNull(DB::connection('supabase')->table('submissions')->where('id', $this->submissionId)->value('score'));
    }
}
