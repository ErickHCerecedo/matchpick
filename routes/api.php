<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\QuinielaController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\StandingController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MatchResultController;
use Illuminate\Support\Facades\Route;

// Auth - public
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
});

// Tournaments - public
Route::get('/tournaments', [TournamentController::class, 'index']);
Route::get('/tournaments/{slug}', [TournamentController::class, 'show']);
Route::get('/tournaments/{slug}/matches', [TournamentController::class, 'matches']);
Route::get('/tournaments/{slug}/global-standings', [TournamentController::class, 'globalStandings']);
Route::get('/tournaments/{slug}/public-quinielas', [TournamentController::class, 'publicQuinielas']);

// Invitations - mixed
Route::get('/invitations/{token}', [InvitationController::class, 'show']);
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('auth:sanctum');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('quinielas', QuinielaController::class)->parameters(['quinielas' => 'slug']);
    Route::get('/quinielas/{slug}/standings', [StandingController::class, 'index']);
    Route::get('/quinielas/{slug}/matches', [QuinielaController::class, 'matches']);
    Route::post('/quinielas/{slug}/predictions', [PredictionController::class, 'bulkUpsert']);
    Route::get('/quinielas/{slug}/predictions/{matchId}', [PredictionController::class, 'matchPredictions']);
    Route::post('/quinielas/{slug}/invitations', [InvitationController::class, 'store']);
});

// Admin routes
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::post('/matches/{match}/results', [MatchResultController::class, 'store']);
});
