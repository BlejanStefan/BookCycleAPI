<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ListingController;
use App\Models\Community;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
Route::middleware('auth:sanctum')->post('/user/update', [App\Http\Controllers\Api\AuthController::class, 'updateProfile']);

Route::post('/login', [AuthController::class, 'login']);
// Ruta de Registro de Usuario
Route::post('/register', [AuthController::class, 'register']);

// Rutas Auxiliares para cargar los Selectores Geográficos en la App
Route::get('/communities', function () {
    return response()->json(Community::orderBy('name')->get());
});

Route::get('/provinces/{community_id}', function ($community_id) {
    return response()->json(Province::where('community_id', $community_id)->orderBy('name')->get());
});

Route::get('/municipalities/{province_id}', function ($province_id) {
    return response()->json(Municipality::where('province_id', $province_id)->orderBy('name')->get());
});
Route::get('/listings/random', [ListingController::class, 'index']);
