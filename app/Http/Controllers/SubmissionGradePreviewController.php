<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\Gemini\GeminiObservationException;
use App\Services\Gemini\GradingTemporarilyUnavailableException;
use App\Services\Gemini\HandwritingGradingService;
use App\Services\Grading\GradingNormalizationException;
use App\Services\Grading\GradingResultNormalizer;
use App\Services\Grading\ScoreCalculator;
use App\Services\Storage\SupabaseStorageException;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Throwable;

class SubmissionGradePreviewController extends Controller
{
    public function store(
        Submission $submission,
        SupabaseStorageService $storage,
        HandwritingGradingService $grading,
        GradingResultNormalizer $normalizer,
        ScoreCalculator $scoreCalculator,
    ): JsonResponse {
        $lesson = $submission->lesson;

        if ($lesson === null) {
            return response()->json(['message' => 'Lesson not found.'], 404);
        }

        try {
            $image = $storage->retrieve($submission->image_path);
            $observation = $grading->observe($lesson->word_list, $image['bytes'], $image['mime_type']);
            $normalizedResults = $normalizer->normalize($observation['results'], $lesson->word_list);
            $score = $scoreCalculator->calculate($normalizedResults);
        } catch (GradingTemporarilyUnavailableException $exception) {
            report($exception);

            return response()->json([
                'error' => [
                    'code' => GradingTemporarilyUnavailableException::CODE,
                    'message' => $exception->getMessage(),
                    'retryable' => true,
                ],
                'submissionId' => (string) $submission->getKey(),
            ], 503);
        } catch (SupabaseStorageException|GeminiObservationException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to preview handwriting grading.',
            ], $exception->status);
        } catch (GradingNormalizationException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to normalize handwriting grading.',
            ], 422);
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
            'normalized_results' => $normalizedResults,
            'score' => $score,
        ]);
    }
}
