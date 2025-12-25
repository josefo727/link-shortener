<?php

declare(strict_types=1);

use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/{code}', RedirectController::class)
    ->where('code', '[a-zA-Z0-9]{1,10}')
    ->name('redirect');
