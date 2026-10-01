<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\LangController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCatalogoController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ProdutosController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\SubscriptionsController as AdminSubscriptionsController;
use App\Livewire\Onboarding\EmpresaStep;
use App\Livewire\Onboarding\PlanoStep;
use Illuminate\Support\Facades\Route;

// Language switcher
Route::get('/lang/{locale}', [LangController::class, 'switch'])->name('lang.switch');

// Public catalog
Route::get('/loja/{slug}', [PublicCatalogoController::class, 'show'])->name('catalogo.show');
Route::post('/loja/{slug}/lead', [PublicCatalogoController::class, 'capturarLead'])->name('catalogo.lead');

// Webhook (no CSRF, no auth — Twilio signature required)
Route::post('/webhook/twilio', [WebhookController::class, 'twilio'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('twilio.signature')
    ->name('webhook.twilio');

Route::post('/webhook/stripe', '\Laravel\Cashier\Http\Controllers\WebhookController@handleWebhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.stripe');

// Breeze auth routes
require __DIR__ . '/auth.php';

// Onboarding: cadastro (guest)
Route::middleware('guest')->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/conta', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/conta', [RegisteredUserController::class, 'store'])->name('register.store');
});

// Onboarding (auth, sem middleware onboarding para evitar loop)
Route::middleware(['auth', 'set.locale'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/empresa', EmpresaStep::class)->name('empresa');
    Route::get('/plano', PlanoStep::class)->name('plano');
    Route::get('/pagamento', [OnboardingController::class, 'pagamento'])->name('pagamento');
    Route::post('/pagamento', [OnboardingController::class, 'processarPagamento'])->name('pagamento.processar');
});

// Profile (Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authenticated app routes (com middleware onboarding)
Route::middleware(['auth', 'set.locale', 'onboarding'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
    Route::put('/empresa', [EmpresaController::class, 'update'])->name('empresa.update');

    Route::get('/produtos', [ProdutosController::class, 'index'])->name('produtos.index');

    Route::get('/assinatura', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/assinatura/cancelar', [BillingController::class, 'cancel'])->name('billing.cancel');

    Route::get('/chat', fn () => view('chat.index'))->name('chat.index');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
});

// Admin (master admin only)
Route::middleware(['auth', 'set.locale', 'master.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/assinaturas', [AdminSubscriptionsController::class, 'index'])->name('subscriptions.index');
});
