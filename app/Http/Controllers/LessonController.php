<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    public function __construct(private readonly Lesson $lesson) {}

    public function index(): Response
    {
        $lessons = $this->lesson->newQuery()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Lesson $lesson): array => [
                'id' => (string) $lesson->getKey(),
                'title' => $lesson->title,
                'moe_level' => $lesson->moe_level,
                'word_list' => $lesson->word_list,
            ])
            ->values();

        return Inertia::render('Lessons', [
            'lessons' => $lessons,
        ]);
    }
}
