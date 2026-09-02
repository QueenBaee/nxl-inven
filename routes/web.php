<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

Route::get('/admin/transactions/{transaction}/receipt', [ReceiptController::class, 'show'])
    ->name('receipt.show')
    ->middleware(['web', 'auth']);
