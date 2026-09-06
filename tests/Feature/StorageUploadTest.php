<?php

namespace Tests\Feature;

use App\Services\Storage\SupabaseStorageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorageUploadTest extends TestCase
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

        DB::connection('supabase')->table('lessons')->insert([
            'id' => $this->lessonId,
            'title' => 'Lesson One',
            'moe_level' => 'P2',
            'word_list' => '{"caochang","litang","laoshi"}',
        ]);
    }

    public function test_missing_image_is_rejected(): void
    {
        $response = $this->postUpload([
            'lesson_id' => $this->lessonId,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $response = $this->postUpload([
            'lesson_id' => $this->lessonId,
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_invalid_lesson_id_is_rejected(): void
    {
        $response = $this->postUpload([
            'lesson_id' => 'not-a-uuid',
            'image' => UploadedFile::fake()->image('handwriting.jpg'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('lesson_id');
    }

    public function test_oversized_image_is_rejected(): void
    {
        $response = $this->postUpload([
            'lesson_id' => $this->lessonId,
            'image' => UploadedFile::fake()->create('large.jpg', 5121, 'image/jpeg'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_storage_service_posts_the_image_with_safe_headers(): void
    {
        Http::fake([
            'https://example.supabase.co/*' => Http::response(['Key' => 'stored'], 200),
        ]);

        $path = app(SupabaseStorageService::class)->upload(
            UploadedFile::fake()->image('handwriting.png'),
            $this->lessonId.'/upload-id.png',
        );

        self::assertSame($this->lessonId.'/upload-id.png', $path);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://example.supabase.co/storage/v1/object/handwriting-submissions/'.$this->lessonId.'/upload-id.png'
                && $request->hasHeader('Authorization', 'Bearer test-secret-key')
                && $request->hasHeader('apikey', 'test-secret-key')
                && $request->hasHeader('Content-Type', 'image/png')
                && $request->hasHeader('x-upsert', 'false');
        });
    }

    public function test_upload_endpoint_returns_path_without_exposing_secret(): void
    {
        Http::fake([
            'https://example.supabase.co/*' => Http::response(['Key' => 'stored'], 200),
        ]);

        $response = $this->postUpload([
            'lesson_id' => $this->lessonId,
            'image' => UploadedFile::fake()->image('handwriting.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('bucket', 'handwriting-submissions')
            ->assertJsonStructure(['bucket', 'image_path'])
            ->assertJsonMissing(['secret_key' => 'test-secret-key']);

        self::assertMatchesRegularExpression(
            '/^'.preg_quote($this->lessonId, '/').'\/[0-9a-f-]{36}\.jpg$/',
            $response->json('image_path'),
        );
    }

    public function test_storage_failure_returns_a_safe_error(): void
    {
        Http::fake([
            'https://example.supabase.co/*' => Http::response(['message' => 'Unauthorized'], 403),
        ]);

        $response = $this->postUpload([
            'lesson_id' => $this->lessonId,
            'image' => UploadedFile::fake()->image('handwriting.jpg'),
        ]);

        $response->assertStatus(502)
            ->assertExactJson(['message' => 'Unable to upload handwriting image.'])
            ->assertDontSee('test-secret-key')
            ->assertDontSee('Unauthorized');
    }

    private function postUpload(array $data)
    {
        return $this->withHeader('Accept', 'application/json')
            ->post('/submissions/upload', $data);
    }
}
