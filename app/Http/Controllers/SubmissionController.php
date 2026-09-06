<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadHandwritingImageRequest;
use App\Models\Submission;
use App\Services\Storage\SupabaseStorageException;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SubmissionController extends Controller
{
    public function store(
        UploadHandwritingImageRequest $request,
        SupabaseStorageService $storage,
    ): JsonResponse {
        $lessonId = $request->string('lesson_id')->toString();
        $image = $request->file('image');
        $path = $lessonId.'/'.Str::uuid().'.'.$image->extension();

        try {
            $storage->upload($image, $path);
        } catch (SupabaseStorageException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to upload handwriting image.',
            ], $exception->status);
        }

        try {
            $submission = Submission::create([
                'id' => (string) Str::uuid(),
                'lesson_id' => $lessonId,
                'student_id' => $request->input('student_id'),
                'image_path' => $path,
                'score' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            try {
                $storage->delete($path);
            } catch (Throwable $cleanupException) {
                Log::error('Submission Storage cleanup failed.', [
                    'path' => $path,
                    'exception' => get_class($cleanupException),
                ]);
            }

            return response()->json([
                'message' => 'Unable to create submission.',
            ], 500);
        }

        return response()->json([
            'submission' => [
                'id' => (string) $submission->getKey(),
                'lesson_id' => (string) $submission->lesson_id,
                'student_id' => $submission->student_id,
                'image_path' => $submission->image_path,
                'score' => $submission->score,
                'created_at' => $submission->created_at?->toISOString(),
            ],
        ], 201);
    }
}
