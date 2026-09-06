<?php

namespace App\Services\Grading;

use App\Models\CharacterResult;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class GradingResultPersister
{
    public function persist(Submission $submission, array $normalizedResults, int $score): void
    {
        try {
            DB::connection('supabase')->transaction(function () use ($submission, $normalizedResults, $score): void {
                $lockedSubmission = Submission::query()
                    ->whereKey($submission->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedSubmission->score !== null
                    || CharacterResult::query()->where('submission_id', $lockedSubmission->getKey())->exists()) {
                    throw new GradingPersistenceException(status: 409);
                }

                foreach ($normalizedResults as $result) {
                    CharacterResult::create([
                        'id' => (string) Str::uuid(),
                        'submission_id' => (string) $lockedSubmission->getKey(),
                        'character_name' => $result['characterName'],
                        'recognized_text' => $result['recognizedText'],
                        'is_correct' => $result['isCorrect'],
                    ]);
                }

                $lockedSubmission->score = $score;
                $lockedSubmission->save();
            });
        } catch (GradingPersistenceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GradingPersistenceException(previous: $exception);
        }
    }
}
