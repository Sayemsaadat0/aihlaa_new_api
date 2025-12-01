<?php

// Add these routes to routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TwilioController;

// Twilio messaging routes (public or protected as needed)
Route::prefix('twilio')->group(function () {
    Route::post('/send', [TwilioController::class, 'send']);
    Route::post('/test', [TwilioController::class, 'test']);
});

// Or if you want them under /api/v1:
Route::prefix('v1')->group(function () {
    Route::post('/twilio/send', [TwilioController::class, 'send']);
    Route::post('/twilio/test', [TwilioController::class, 'test']);
});

