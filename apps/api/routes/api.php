<?php

use App\Http\Controllers\Api\V1\AdministrativeAreaController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BracketController;
use App\Http\Controllers\Api\V1\BracketMatchController;
use App\Http\Controllers\Api\V1\CompetitionController;
use App\Http\Controllers\Api\V1\DivisionController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\GameBoxScoreController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\LiveGameController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationInvitationController;
use App\Http\Controllers\Api\V1\OrganizationMemberController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PlayerController;
use App\Http\Controllers\Api\V1\PlayerRegistrationController;
use App\Http\Controllers\Api\V1\PublicCompetitionController;
use App\Http\Controllers\Api\V1\PublicTeamController;
use App\Http\Controllers\Api\V1\SeasonController;
use App\Http\Controllers\Api\V1\SeasonTeamRegistrationController;
use App\Http\Controllers\Api\V1\StandingController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\VenueController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'service' => 'grassroots-basketball-api',
                'environment' => app()->environment(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    })->name('api.v1.health');

    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1');
        });
    });

    Route::get('/invitations/{token}', [OrganizationInvitationController::class, 'show'])
        ->middleware('throttle:30,1');
    Route::post('/invitations/{token}/register', [OrganizationInvitationController::class, 'register'])
        ->middleware('throttle:6,1');
    Route::get('/public/organizations/{organization}/teams/{team}', [PublicTeamController::class, 'show']);
    Route::get('/public/organizations/{organization}/competitions/{competition}/seasons/{season}', [PublicCompetitionController::class, 'show']);
    Route::get('/public/organizations/{organization}/competitions/{competition}/seasons/{season}/games', [PublicCompetitionController::class, 'games']);
    Route::get('/public/organizations/{organization}/competitions/{competition}/seasons/{season}/games/{game}', [PublicCompetitionController::class, 'game']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/administrative-areas', [AdministrativeAreaController::class, 'index']);
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::post('/invitations/accept', [OrganizationInvitationController::class, 'accept']);

        Route::middleware('verified')->group(function (): void {
            Route::post('/administrative-areas', [AdministrativeAreaController::class, 'store']);
            Route::post('/organizations', [OrganizationController::class, 'store']);
            Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
            Route::patch('/organizations/{organization}', [OrganizationController::class, 'update']);
            Route::get('/organizations/{organization}/members', [OrganizationMemberController::class, 'index']);
            Route::patch('/organizations/{organization}/members/{membership}', [OrganizationMemberController::class, 'update']);
            Route::delete('/organizations/{organization}/members/{membership}', [OrganizationMemberController::class, 'destroy']);
            Route::get('/organizations/{organization}/invitations', [OrganizationInvitationController::class, 'index']);
            Route::post('/organizations/{organization}/invitations', [OrganizationInvitationController::class, 'store']);
            Route::get('/organizations/{organization}/venues', [VenueController::class, 'index']);
            Route::post('/organizations/{organization}/venues', [VenueController::class, 'store']);
            Route::patch('/organizations/{organization}/venues/{venue}', [VenueController::class, 'update']);
            Route::get('/organizations/{organization}/competitions', [CompetitionController::class, 'index']);
            Route::post('/organizations/{organization}/competitions', [CompetitionController::class, 'store']);
            Route::get('/organizations/{organization}/competitions/{competition}', [CompetitionController::class, 'show']);
            Route::patch('/organizations/{organization}/competitions/{competition}', [CompetitionController::class, 'update']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons', [SeasonController::class, 'store']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}', [SeasonController::class, 'update']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/divisions', [DivisionController::class, 'store']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/divisions/{division}', [DivisionController::class, 'update']);
            Route::delete('/organizations/{organization}/competitions/{competition}/seasons/{season}/divisions/{division}', [DivisionController::class, 'destroy']);
            Route::get('/organizations/{organization}/teams', [TeamController::class, 'index']);
            Route::post('/organizations/{organization}/teams', [TeamController::class, 'store']);
            Route::patch('/organizations/{organization}/teams/{team}', [TeamController::class, 'update']);
            Route::post('/organizations/{organization}/teams/{team}/logo', [TeamController::class, 'uploadLogo']);
            Route::get('/organizations/{organization}/players', [PlayerController::class, 'index']);
            Route::post('/organizations/{organization}/players', [PlayerController::class, 'store']);
            Route::patch('/organizations/{organization}/players/{player}', [PlayerController::class, 'update']);
            Route::post('/organizations/{organization}/players/{player}/photo', [PlayerController::class, 'uploadPhoto']);
            Route::get('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations', [SeasonTeamRegistrationController::class, 'index']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations', [SeasonTeamRegistrationController::class, 'store']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations/{teamRegistration}', [SeasonTeamRegistrationController::class, 'update']);
            Route::delete('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations/{teamRegistration}', [SeasonTeamRegistrationController::class, 'destroy']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations/{teamRegistration}/players', [PlayerRegistrationController::class, 'store']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations/{teamRegistration}/players/create', [PlayerRegistrationController::class, 'create']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations/{teamRegistration}/players/{playerRegistration}', [PlayerRegistrationController::class, 'update']);
            Route::delete('/organizations/{organization}/competitions/{competition}/seasons/{season}/team-registrations/{teamRegistration}/players/{playerRegistration}', [PlayerRegistrationController::class, 'destroy']);
            Route::get('/organizations/{organization}/competitions/{competition}/seasons/{season}/games', [GameController::class, 'index']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/games', [GameController::class, 'store']);
            Route::get('/organizations/{organization}/competitions/{competition}/seasons/{season}/games/{game}', [GameController::class, 'show']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/games/{game}', [GameController::class, 'update']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/games/{game}/live-actions', [LiveGameController::class, 'store']);
            Route::put('/organizations/{organization}/competitions/{competition}/seasons/{season}/games/{game}/lineup', [GameBoxScoreController::class, 'lineup']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/games/{game}/substitutions', [GameBoxScoreController::class, 'substitute']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/games/{game}/player-stats/{playerStat}', [GameBoxScoreController::class, 'update']);
            Route::get('/organizations/{organization}/competitions/{competition}/seasons/{season}/announcements', [AnnouncementController::class, 'index']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/announcements', [AnnouncementController::class, 'store']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/announcements/{announcement}', [AnnouncementController::class, 'update']);
            Route::delete('/organizations/{organization}/competitions/{competition}/seasons/{season}/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
            Route::get('/organizations/{organization}/competitions/{competition}/seasons/{season}/standings', [StandingController::class, 'index']);
            Route::put('/organizations/{organization}/competitions/{competition}/seasons/{season}/standings', [StandingController::class, 'update']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/standings/recalculate', [StandingController::class, 'recalculate']);
            Route::get('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets', [BracketController::class, 'index']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets', [BracketController::class, 'store']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets/{bracket}/initialize', [BracketController::class, 'initialize']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets/{bracket}', [BracketController::class, 'update']);
            Route::delete('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets/{bracket}', [BracketController::class, 'destroy']);
            Route::post('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets/{bracket}/matches', [BracketMatchController::class, 'store']);
            Route::patch('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets/{bracket}/matches/{bracketMatch}', [BracketMatchController::class, 'update']);
            Route::delete('/organizations/{organization}/competitions/{competition}/seasons/{season}/brackets/{bracket}/matches/{bracketMatch}', [BracketMatchController::class, 'destroy']);
        });
    });
});
