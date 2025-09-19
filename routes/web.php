<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ContactController;

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/events', [PageController::class, 'events'])->name('events.index');
Route::get('/events/{slug}', [PageController::class, 'eventShow'])->name('events.show');

// Contact
Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

// Auth helpers (Filament uses /admin auth but we add a simple logout POST route alias)
Route::middleware('auth')->group(function () {
	Route::post('/logout', function () {
		\Illuminate\Support\Facades\Auth::logout();
		request()->session()->invalidate();
		request()->session()->regenerateToken();
		return redirect('/');
	})->name('logout');
});
