<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Livewire\ManageAccounts;
use App\Livewire\ManageCurrencies;
use App\Livewire\ManageCurrencyPairs;
use App\Livewire\ManageInstitutions;
use App\Livewire\ManageLiabilities;
use App\Livewire\ManageTransactions;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/transactions', ManageTransactions::class)->name('transactions');
    Route::get('/institutions', ManageInstitutions::class)->name('institutions');
    Route::get('/currencies', ManageCurrencies::class)->name('currencies');
    Route::get('/accounts', ManageAccounts::class)->name('accounts');
    Route::get('/liabilities', ManageLiabilities::class)->name('liabilities');
    Route::get('/currency-pairs', ManageCurrencyPairs::class)->name('currency-pairs');
});

require __DIR__.'/auth.php';
