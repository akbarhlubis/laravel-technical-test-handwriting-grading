<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\Gemini\GeminiObservationException;
use App\Services\Gemini\HandwritingGradingService;
use App\Services\Storage\SupabaseStorageException;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Throwable;

class SubmissionGradePreviewController extends Controller
{
    public function store (
        Submission $submission,
        SupabaseStorageService $storage,
        HandwritingGradingService $grading,
        ): JsonResponse {
            $lesson = $submission->lesson;

            if ($lesson === null) {
                return response()->json(['message' => 'Lesson not found.'], 404);
            }

            try {
                $image = $storage->retrieve($submission->image_path);
                $observation = $grading->observe($lesson->word_list, $image['bytes'], $image['mime_type']);
            } catch (SupabaseStorageException|GeminiObservationException $exception) {
                report($exception);

                return response()->json([
                    'message' => 'Unable to preview handwriting grading.',
                ], $exception->status);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'Unable to preview handwriting grading.',
                ], 502);
            }

            return response()->json([
                'submission_id' => (string) $submission->getKey(),
                'expected_words' => $lesson->word_list,
                'observation' => $observation,
            ]);
        }
}
