<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TeamController;

Route::get('/health', [TeamController::class, 'health']);

Route::get('/teams',         [TeamController::class, 'index']);
Route::get('/teams/{id}',    [TeamController::class, 'show']);
Route::post('/teams',        [TeamController::class, 'store']);
Route::put('/teams/{id}',    [TeamController::class, 'update']);
Route::delete('/teams/{id}', [TeamController::class, 'destroy']);
