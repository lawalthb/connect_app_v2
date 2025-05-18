<?php

use App\Http\Controllers\API\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-mail', function () {
    try {
        Mail::raw('Test email from ConnectApp', function ($message) {
            $message->to('lawalthb@gmail.com')
                    ->subject('Test Email');
        });
        return 'Email sent successfully!';
    } catch (\Exception $e) {
        return 'Email error: ' . $e->getMessage();
    }
});
// Google OAuth routes - moved to web middleware
Route::get('api/v1/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('api/v1/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
