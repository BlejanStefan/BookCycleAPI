<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConversationController;
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

Route::get('/municipalities/{id}/hierarchy', function ($id) {
    $hierarchy = DB::table('municipalities')
        ->join('provinces', 'municipalities.province_id', '=', 'provinces.id')
        ->where('municipalities.id', $id)
        ->select(
            'municipalities.id as municipality_id',
            'provinces.id as province_id',
            'provinces.community_id as community_id'
        )
        ->first();

    return response()->json($hierarchy);
});
Route::get('/municipalities/{province_id}', function ($province_id) {
    return response()->json(Municipality::where('province_id', $province_id)->orderBy('name')->get());
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/my-listings', [ListingController::class, 'myListings']);
    Route::post('/listings/{id}/favorite', [ListingController::class, 'toggleFavorite']);
    Route::put('/listings/{id}', [ListingController::class, 'update']);
    Route::delete('/listings/{id}', [ListingController::class, 'destroy']);
    Route::get('/listings/favorites/me', [ListingController::class, 'getFavorites']);
    Route::get('/books/isbn/{isbn}', [BookController::class, 'checkByIsbn']);
    Route::post('/listings', [ListingController::class, 'store']);
    // 📬 Para el InboxViewModel (Listado de todas las salas de chat)
    Route::get('/conversations', [ConversationController::class, 'index']);

    // 💬 Para el ChatViewModel (Ver una conversación por dentro y enviar mensajes)
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'storeMessage']);

});
Route::get('/listings', [ListingController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/listings/{id}', [ListingController::class, 'show']);

