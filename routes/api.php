<?php

use App\Http\Controllers\Api\NodeEnrollmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/node-enrollments')->group(function (): void {
    Route::post('/device', [NodeEnrollmentController::class, 'device'])->middleware('throttle:node-enrollment-device');
    Route::post('/device/token', [NodeEnrollmentController::class, 'poll'])->middleware('throttle:node-enrollment-poll');
    Route::post('/automatic', [NodeEnrollmentController::class, 'automatic'])->middleware('throttle:node-enrollment-device');
    Route::post('/{enrollment:uuid}/progress', [NodeEnrollmentController::class, 'progress'])->middleware('throttle:node-enrollment-bootstrap');
    Route::post('/{enrollment:uuid}/certificate', [NodeEnrollmentController::class, 'certificate'])->middleware('throttle:node-enrollment-bootstrap');
    Route::post('/{enrollment:uuid}/complete', [NodeEnrollmentController::class, 'complete'])->middleware('throttle:node-enrollment-bootstrap');
});
