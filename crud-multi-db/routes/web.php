<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BookController;

Route::get('/', function () {
    return view('welcome');
});

// User routes
Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::post('users', [UserController::class, 'store'])->name('users.store');
Route::put('users/{id}', [UserController::class, 'update'])->name('users.update');
Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
Route::post('users/sync/{id}', [UserController::class, 'sync'])->name('users.sync');
Route::post('users/sync-all', [UserController::class, 'syncAll'])->name('users.syncAll');

// Book routes (nested under users)
Route::get('users/{userId}/books', [BookController::class, 'index'])->name('books.index');
Route::get('users/{userId}/books/create', [BookController::class, 'create'])->name('books.create');
Route::post('users/{userId}/books', [BookController::class, 'store'])->name('books.store');
Route::get('users/{userId}/books/{bookId}/edit', [BookController::class, 'edit'])->name('books.edit');
Route::put('users/{userId}/books/{bookId}', [BookController::class, 'update'])->name('books.update');
Route::delete('users/{userId}/books/{bookId}', [BookController::class, 'destroy'])->name('books.destroy');

// All books view (across all users)
Route::get('books/all', [BookController::class, 'allBooks'])->name('books.all');
