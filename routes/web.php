<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlayerController;

// Auth Routes
Route::get('/login', [AuthController::class, 'show_login'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

Route::get('/register', [AuthController::class, 'show_register'])->name('register')->middleware('guest');
Route::post('/register', [AuthController::class, 'register'])->middleware('guest');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/', [PlayerController::class, 'index'])->name('home');
    Route::post('/track', [PlayerController::class, 'track_player'])->name('track.player');
    Route::delete('/untrack/{id}', [PlayerController::class, 'untrack_player'])->name('untrack.player');
});
