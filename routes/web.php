<?php

use App\Http\Controllers\LessonController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
});

Route::get('/lessons', [LessonController::class, 'index']);

Route::post('/submissions', [SubmissionController::class, 'store']);
