<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['throttle:60,1'])->group(function () {

	Route::post('login', [ApiController::class, 'login']);
	//
	Route::get('my-tickets', [ApiController::class, 'myTickets']);

	Route::post('send-fcm-push', [ApiController::class, 'sendFCMPush']);

	Route::post('update-user-balance', [ApiController::class, 'updateUserBalance']);

	Route::get('/ticket-details/{id}', [ApiController::class, 'ticketDetails']);

	Route::middleware('auth:api')->group(function () {
		Route::post('update-device-token', [ApiController::class, 'updateDeviceToken']);
		Route::get('/ticket-logs', [ApiController::class, 'ticketLogs']);
		
		Route::post('edit-ticket', [ApiController::class, 'editTicket']);
		Route::get('/me', [ApiController::class, 'me']);
		Route::post('/logout', [ApiController::class, 'logout']);
	});
});