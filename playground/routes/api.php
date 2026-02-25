<?php

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| D1 Database Demo — CRUD API for Notes
|--------------------------------------------------------------------------
|
| These routes demonstrate Cloudflare D1 database integration via the
| cfd1 PDO driver. When deployed to Cloudflare Workers, these endpoints
| read/write to a D1 SQLite-compatible database.
|
*/

Route::get('/notes', [NoteController::class, 'index']);
Route::post('/notes', [NoteController::class, 'store']);
Route::get('/notes/{note}', [NoteController::class, 'show']);
Route::put('/notes/{note}', [NoteController::class, 'update']);
Route::delete('/notes/{note}', [NoteController::class, 'destroy']);
