<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::post('users', [UserController::class, 'store'])->name('users.store');
Route::put('users/{id}', [UserController::class, 'update'])->name('users.update');
Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
Route::post('users/sync/{id}', [UserController::class, 'sync'])->name('users.sync');
// Sync all users to DB2
Route::post('users/sync-all', [UserController::class, 'syncAll'])->name('users.syncAll');
