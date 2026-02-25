<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/performance', [PageController::class, 'performance'])->name('performance');
Route::get('/architecture', [PageController::class, 'architecture'])->name('architecture');
Route::get('/features', [PageController::class, 'features'])->name('features');
