<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    private string $lessonId = '865dc30c-a821-4589-b088-a4a96d883541';

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
            'services.supabase.secret_key' => 'test-secret-key',
            'services.supabase.storage_bucket' => 'handwriting-submissions',
        ]);

        DB::purge('supabase');
        Schema::connection('supabase')->create('lessons', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('moe_level');
            $table->text('word_list');
            $table->timestamp('created_at')->nullable();
        });
        Schema::connection('supabase')->create('submissions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('lesson_id');
            $table->string('student_id')->nullable();
            $table->string('image_path');
            $table->integer('score')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::connection('supabase')->table('lessons')->insert([
            'id' => $this->lessonId,
            'title' => 'Lesson One',
            'moe_level' => 'P2',
            'word_list' => '{"caochang","litang","laoshi"}',
        ]);
    }

    public function test_valid_submission_is_persisted_after_storage_upload(): void
    {
        Http::fake(fn (Request $request) => Http::response(['Key' => 'stored'], 200));

        $response = $this->postSubmission();
        $submission = $response->json('submission');

        $response->assertCreated()
            ->assertJsonPath('submission.lesson_id', $this->lessonId)
            ->assertJsonPath('submission.student_id', null)
            ->assertJsonPath('submission.score', null)
            ->assertJsonStructure(['submission' => ['id', 'lesson_id', 'student_id', 'image_path', 'score', 'created_at']])
            ->assertDontSee('test-secret-key');

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $submission['id']);
        self::assertMatchesRegularExpression(
            '/^'.preg_quote($this->lessonId, '/').'\/[0-9a-f-]{36}\.jpg$/',
            $submission['image_path'],
        );
        self::assertNotNull($submission['created_at']);
        self::assertSame(1, DB::connection('supabase')->table('submissions')->count());
    }

    public function test_student_id_is_optional_and_persisted_when_provided(): void
    {
        Http::fake(fn (Request $request) => Http::response(['Key' => 'stored'], 200));

        $response = $this->postSubmission(['student_id' => 'student-001']);

        $response->assertCreated()->assertJsonPath('submission.student_id', 'student-001');
    }

    public function test_validation_failure_does_not_upload_or_insert(): void
    {
        Http::fake();

        $response = $this->postSubmission([
            'lesson_id' => 'not-a-uuid',
        ], includeImage: false);

        $response->assertStatus(422)->assertJsonValidationErrors(['lesson_id', 'image']);
        Http::assertNothingSent();
        self::assertSame(0, DB::connection('supabase')->table('submissions')->count());
    }

    public function test_storage_failure_does_not_insert(): void
    {
        Http::fake(fn (Request $request) => Http::response(['message' => 'Unauthorized'], 403));

        $response = $this->postSubmission();

        $response->assertStatus(502)
            ->assertExactJson(['message' => 'Unable to upload handwriting image.'])
            ->assertDontSee('test-secret-key')
            ->assertDontSee('Unauthorized');
        self::assertSame(0, DB::connection('supabase')->table('submissions')->count());
    }

    public function test_database_failure_deletes_the_uploaded_object(): void
    {
        $uploadedPath = null;
        $deletedPath = null;
        Http::fake(function (Request $request) use (&$uploadedPath, &$deletedPath) {
            $prefix = 'https://example.supabase.co/storage/v1/object/handwriting-submissions/';
            $path = str_replace($prefix, '', $request->url());

            if ($request->method() === 'POST') {
                $uploadedPath = $path;

                return Http::response(['Key' => 'stored'], 200);
            }

            $deletedPath = $path;

            return Http::response([], 204);
        });
        Schema::connection('supabase')->drop('submissions');

        $response = $this->postSubmission();

        $response->assertStatus(500)->assertExactJson(['message' => 'Unable to create submission.']);
        self::assertNotNull($uploadedPath);
        self::assertSame($uploadedPath, $deletedPath);
    }

    public function test_cleanup_failure_returns_safe_error_and_logs_the_object_path(): void
    {
        $uploadedPath = null;
        Log::spy();
        Http::fake(function (Request $request) use (&$uploadedPath) {
            $prefix = 'https://example.supabase.co/storage/v1/object/handwriting-submissions/';

            if ($request->method() === 'POST') {
                $uploadedPath = str_replace($prefix, '', $request->url());

                return Http::response(['Key' => 'stored'], 200);
            }

            return Http::response(['message' => 'cleanup denied'], 403);
        });
        Schema::connection('supabase')->drop('submissions');

        $response = $this->postSubmission();

        $response->assertStatus(500)
            ->assertExactJson(['message' => 'Unable to create submission.'])
            ->assertDontSee('test-secret-key')
            ->assertDontSee('cleanup denied');
        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($uploadedPath): bool {
            return $message === 'Submission Storage cleanup failed.'
                && $context['path'] === $uploadedPath;
        });
    }

    private function postSubmission(array $extra = [], bool $includeImage = true)
    {
        $data = array_merge(['lesson_id' => $this->lessonId], $extra);

        if ($includeImage) {
            $data['image'] = UploadedFile::fake()->image('handwriting.jpg');
        }

        return $this->withHeader('Accept', 'application/json')
            ->post('/submissions', $data);
    }
}
