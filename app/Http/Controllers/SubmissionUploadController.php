<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadHandwritingImageRequest;
use App\Services\Storage\SupabaseStorageException;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SubmissionUploadController extends Controller
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

        return response()->json([
            'bucket' => config('services.supabase.storage_bucket', 'handwriting-submissions'),
            'image_path' => $path,
        ], 201);
    }
}
