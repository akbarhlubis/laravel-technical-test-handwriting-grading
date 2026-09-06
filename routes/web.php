<?php

use App\Http\Controllers\LessonController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionGradeController;
use App\Http\Controllers\SubmissionGradePreviewController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::get('/lessons', [LessonController::class, 'index']);

Route::post('/submissions', [SubmissionController::class, 'store']);
Route::post('/submissions/{submission}/grade-preview', [SubmissionGradePreviewController::class, 'store']);
Route::post('/submissions/{submission}/grade', [SubmissionGradeController::class, 'store']);
