<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\CustomTournamentController;
use App\Http\Controllers\QuinielaController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\StandingController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MatchResultController;
use App\Http\Controllers\QuinielaResultController;
use App\Http\Controllers\WildcardController;
use App\Http\Controllers\Admin\TournamentAdminController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Auth - public
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
});

// Site settings - public read
Route::get('/site-settings', [SiteSettingController::class, 'show']);

// Matches - public
Route::get('/matches/live', [TournamentController::class, 'liveMatches']);

// Tournaments - public
Route::get('/tournaments', [TournamentController::class, 'index']);
Route::get('/tournaments/{slug}', [TournamentController::class, 'show']);
Route::get('/tournaments/{slug}/matches', [TournamentController::class, 'matches']);
Route::get('/tournaments/{slug}/global-standings', [TournamentController::class, 'globalStandings']);
Route::get('/tournaments/{slug}/team-standings', [TournamentController::class, 'teamStandings']);
Route::get('/tournaments/{slug}/public-quinielas', [TournamentController::class, 'publicQuinielas']);

// Invitations - mixed
Route::get('/invitations/{token}', [InvitationController::class, 'show']);
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('auth:sanctum');

// Custom tournaments - public reads
Route::get('/tournaments/{slug}/teams', [CustomTournamentController::class, 'teams']);
Route::get('/tournaments/{slug}/rounds', [CustomTournamentController::class, 'rounds']);
Route::get('/tournaments/{slug}/rounds/{roundId}/matches', [CustomTournamentController::class, 'roundMatches']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Custom tournament management
    Route::post('/tournaments', [CustomTournamentController::class, 'store']);
    Route::patch('/tournaments/{slug}', [CustomTournamentController::class, 'update']);
    Route::get('/my-tournaments', [CustomTournamentController::class, 'mine']);
    Route::post('/tournaments/{slug}/teams', [CustomTournamentController::class, 'addTeam']);
    Route::patch('/tournaments/{slug}/teams/{teamId}', [CustomTournamentController::class, 'updateTeam']);
    Route::delete('/tournaments/{slug}/teams/{teamId}', [CustomTournamentController::class, 'removeTeam']);
    Route::post('/tournaments/{slug}/rounds', [CustomTournamentController::class, 'addRound']);
    Route::delete('/tournaments/{slug}/rounds/{roundId}', [CustomTournamentController::class, 'removeRound']);
    Route::post('/tournaments/{slug}/rounds/{roundId}/matches', [CustomTournamentController::class, 'addMatch']);
    Route::patch('/tournaments/{slug}/rounds/{roundId}/matches/{matchId}', [CustomTournamentController::class, 'updateMatch']);
    Route::delete('/tournaments/{slug}/rounds/{roundId}/matches/{matchId}', [CustomTournamentController::class, 'removeMatch']);
    Route::patch('/tournaments/{slug}/rounds/{roundId}/matches/{matchId}/status', [CustomTournamentController::class, 'updateMatchStatus']);
    Route::post('/tournaments/{slug}/rounds/{roundId}/matches/{matchId}/result', [CustomTournamentController::class, 'setMatchResult']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/profile', [ProfileController::class, 'update']);

    Route::apiResource('quinielas', QuinielaController::class)->parameters(['quinielas' => 'slug']);
    Route::get('/quinielas/{slug}/standings', [StandingController::class, 'index']);
    Route::get('/quinielas/{slug}/matches', [QuinielaController::class, 'matches']);
    Route::post('/quinielas/{slug}/predictions', [PredictionController::class, 'bulkUpsert']);
    Route::get('/quinielas/{slug}/predictions/{matchId}', [PredictionController::class, 'matchPredictions']);
    Route::get('/quinielas/{slug}/participants/{userId}/breakdown', [PredictionController::class, 'participantBreakdown']);
    Route::post('/quinielas/{slug}/invitations', [InvitationController::class, 'store']);
    Route::post('/quinielas/{slug}/matches/{matchId}/result', [QuinielaResultController::class, 'store']);
    Route::post('/quinielas/{slug}/sync-results', [QuinielaResultController::class, 'syncFromApi']);
    // Deletion safety: status + unanimous vote endpoints
    Route::get('/quinielas/{slug}/delete-status', [QuinielaController::class, 'deleteStatus']);
    Route::post('/quinielas/{slug}/delete-votes', [QuinielaController::class, 'castDeleteVote']);
    Route::delete('/quinielas/{slug}/delete-votes', [QuinielaController::class, 'revokeDeleteVote']);

    // Wildcard
    Route::get('/quinielas/{slug}/wildcards', [WildcardController::class, 'index']);
    Route::get('/quinielas/{slug}/wildcard', [WildcardController::class, 'show']);
    Route::post('/quinielas/{slug}/wildcard', [WildcardController::class, 'save']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {
    Route::post('/matches/{match}/results', [MatchResultController::class, 'store']);
    Route::patch('/matches/{match}/status', [MatchResultController::class, 'updateStatus']);
    Route::get('/football-data/test', [MatchResultController::class, 'testFootballData']);
    Route::get('/football-data/export', [MatchResultController::class, 'exportWorldCupData']);
    Route::get('/football-data/match-status', [MatchResultController::class, 'apiMatchStatus']);
    Route::post('/football-data/run-sync', [MatchResultController::class, 'runSync']);
    Route::get('/tournaments', [TournamentAdminController::class, 'index']);
    Route::get('/tournaments/{tournament}/quinielas', [TournamentAdminController::class, 'quinielas']);
    Route::get('/tournaments/{tournament}/wildcard-teams', [TournamentAdminController::class, 'wildcardTeams']);
    Route::put('/tournaments/{tournament}/wildcard-teams', [TournamentAdminController::class, 'setWildcardTeams']);
    Route::get('/tournaments/{tournament}/wildcard-podium', [TournamentAdminController::class, 'wildcardPodium']);
    Route::put('/tournaments/{tournament}/wildcard-podium', [TournamentAdminController::class, 'setWildcardPodium']);
    Route::patch('/quinielas/{quiniela}/wildcard-enabled', [TournamentAdminController::class, 'setQuinielaWildcard']);
    Route::patch('/quinielas/{quiniela}/penalties-enabled', [TournamentAdminController::class, 'setQuinielaPenalties']);
    Route::patch('/tournaments/{tournament}', [TournamentAdminController::class, 'update']);
    Route::patch('/teams/{team}', [TournamentAdminController::class, 'updateTeam']);
    Route::patch('/matches/{match}', [TournamentAdminController::class, 'updateMatch']);
    Route::put('/site-settings', [SiteSettingController::class, 'update']);
});
