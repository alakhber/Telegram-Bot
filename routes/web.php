<?php

use App\Http\Controllers\TelegramBotController;
use App\Service\TelegramBot;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [TelegramBotController::class,'writeMessages']);
Route::get('/connect', [TelegramBotController::class,'connect']);
Route::get('/scan', [TelegramBotController::class,'qrScanner']);
