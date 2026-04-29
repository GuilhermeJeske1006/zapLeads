<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LangController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LojaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCatalogoController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Language switcher
Route::get('/lang/{locale}', [LangController::class, 'switch'])->name('lang.switch');

// Public catalog
Route::get('/loja/{slug}', [PublicCatalogoController::class, 'show'])->name('catalogo.show');
Route::post('/loja/{slug}/lead', [PublicCatalogoController::class, 'capturarLead'])->name('catalogo.lead');

// Webhook (no CSRF, no auth)
Route::post('/webhook/zapi', [WebhookController::class, 'zapi'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.zapi');

// Breeze auth routes
require __DIR__ . '/auth.php';

// Profile (Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authenticated app routes
Route::middleware(['auth', 'set.locale'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('lojas', LojaController::class);
    Route::get('/lojas/{loja}/produtos', function (\App\Models\Loja $loja) {
        abort_unless(auth()->id() === $loja->user_id, 403);
        return view('lojas.produtos', compact('loja'));
    })->name('store.produtos');

    Route::get('/chat', fn () => view('chat.index'))->name('chat.index');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
});
