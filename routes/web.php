<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LibraryExportController;
use App\Http\Controllers\StatisticsController;
use App\Livewire\ArticleDetail;
use App\Livewire\Articles;
use App\Livewire\History;
use App\Livewire\Sources;
use App\Livewire\WatchConfiguration;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('articles', Articles::class)->name('articles.all');
    Route::get('articles/du-jour', Articles::class)->name('articles.today');
    Route::get('articles/interessants', Articles::class)->name('articles.interesting');
    Route::get('articles/a-lire', Articles::class)->name('articles.later');
    Route::get('articles/impertinents', Articles::class)->name('articles.irrelevant');
    Route::get('bibliotheque', Articles::class)->name('articles.library');
    Route::get('bibliotheque/export.pdf', LibraryExportController::class)->middleware('throttle:5,1')->name('articles.export');
    Route::get('articles/{article}', ArticleDetail::class)->name('articles.show');
    Route::get('sources', Sources::class)->middleware('can:manage-watch')->name('sources');
    Route::get('historique', History::class)->name('history');
    Route::get('parametres/veille', WatchConfiguration::class)->middleware('can:manage-watch')->name('watch.settings');
    Route::get('statistiques', StatisticsController::class)->name('statistics');
});

require __DIR__.'/settings.php';
