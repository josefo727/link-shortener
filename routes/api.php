<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ShortUrlController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/urls', [ShortUrlController::class, 'store']);
    Route::put('/urls/{code}', [ShortUrlController::class, 'update']);
    Route::delete('/urls/{code}', [ShortUrlController::class, 'destroy']);
});
