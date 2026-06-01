<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConversationController extends Controller
{
    /**
     * GET /api/conversations
     * Carga el listado de la bandeja de entrada del usuario autenticado
     */
    public function index()
    {
        $userId = auth()->id();

        // Buscamos las conversaciones donde el usuario es comprador o vendedor
        $conversations = DB::table('conversations')
            ->join('listings', 'conversations.listing_id', '=', 'listings.id')
            ->join('books', 'listings.book_id', '=', 'books.id')
            ->select(
                'conversations.id',
                'conversations.buyer_id',
                'conversations.seller_id',
                'listings.id as listing_id',
                'books.title as listing_title'
            )
            ->where('conversations.buyer_id', $userId)
            ->orWhere('conversations.seller_id', $userId)
            ->get();

        $formatted = $conversations->map(function ($chat) use ($userId) {
            // Identificar al otro participante
            $otherUserId = ($chat->buyer_id == $userId) ? $chat->seller_id : $chat->buyer_id;

            $otherUser = DB::table('users')
                ->where('id', $otherUserId)
                ->select('id', 'username')
                ->first();

            // Buscar el último mensaje de esta conversación
            $lastMessage = DB::table('messages')
                ->where('conversation_id', $chat->id)
                ->orderBy('id', 'desc')
                ->select('content', 'created_at')
                ->first();

            return [
                'id' => $chat->id,
                'other_user' => [
                    'id' => $otherUser->id ?? null,
                    'username' => $otherUser->username ?? 'Usuario BookCycle',
                ],
                'listing' => [
                    'id' => $chat->listing_id,
                    'book_title' => $chat->listing_title,
                ],
                'last_message' => [
                    'content' => $lastMessage->content ?? 'Sin mensajes en el chat.',
                    'created_at' => $lastMessage->created_at ?? null,
                ]
            ];
        });
        $sorted = $formatted->sortByDesc(fn($chat) => $chat['last_message']['created_at'])->values();
        return response()->json([
            'success' => true,
            'data' => $sorted
        ], 200);

    }

    /**
     * GET /api/conversations/{id}
     * Entrar a una conversación específica y ver sus mensajes
     */
    public function show($id)
    {
        $userId = auth()->id();

        $conversation = DB::table('conversations')->where('id', $id)->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversación no encontrada.'], 404);
        }

        // Verificar que pertenece al chat
        if ($conversation->buyer_id != $userId && $conversation->seller_id != $userId) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado.'], 403);
        }

        // Obtener la información del libro/anuncio para la cabecera
        $listing = DB::table('listings')
            ->join('books', 'listings.book_id', '=', 'books.id')
            ->select('listings.id', 'books.title as book_title', 'listings.price', 'listings.condition')
            ->where('listings.id', $conversation->listing_id)
            ->first();

        // Obtener todos los mensajes en orden cronológico
        $messages = DB::table('messages')
            ->where('conversation_id', $id)
            ->orderBy('id', 'asc')
            ->select('id', 'conversation_id', 'sender_id', 'content', 'created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int)$id,
                'current_user_id' => $userId, // Importante para alinear las burbujas a derecha/izquierda
                'listing' => $listing,
                'messages' => $messages
            ]
        ], 200);
    }

    /**
     * POST /api/conversations/{id}/messages
     * Almacenar y responder con un nuevo mensaje enviado
     */
    public function storeMessage(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $userId = auth()->id();
        $conversation = DB::table('conversations')->where('id', $id)->first();

        if (!$conversation || ($conversation->buyer_id != $userId && $conversation->seller_id != $userId)) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $now = Carbon::now();

        $messageId = DB::table('messages')->insertGetId([
            'conversation_id' => $id,
            'sender_id' => $userId,
            'content' => $request->input('content'),
            'created_at' => $now,
            'read_at' => null
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $messageId,
                'conversation_id' => (int)$id,
                'sender_id' => $userId,
                'content' => $request->input('content'),
                'created_at' => $now->toDateTimeString()
            ]
        ], 201);
    }
    // En app/Http/Controllers/ConversationController.php

    public function store(Request $request)
    {
        \Log::info('Datos recibidos en store:', $request->all());

        // Validamos que exista en el request, usando el método get para mayor seguridad
        $listingId = $request->input('listing_id');

        if (!$listingId) {
            return response()->json([
                'success' => false,
                'message' => 'No se recibió el ID del anuncio',
                'received' => $request->all() // Esto te ayudará a ver qué nombres de variables llegan
            ], 422);
        }

        // 2. Validación con captura de errores
        $validator = \Validator::make($request->all(), [
            'listing_id' => 'required',
        ]);

        if ($validator->fails()) {
            \Log::error('Errores de validación: ', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validación fallida',
                'errors' => $validator->errors() // Esto se verá en el JSON de respuesta
            ], 422);
        }
        $request->validate([
            'listing_id' => 'required|exists:listings,id',
        ]);

        $buyerId = auth()->id();
        $listing = DB::table('listings')->where('id', $request->listing_id)->first();
        $sellerId = $listing->user_id;

        // 🛡️ Seguridad: No puedes chatear contigo mismo
        if ($buyerId == $sellerId) {
            return response()->json(['success' => false, 'message' => 'No puedes chatear contigo mismo.'], 400);
        }

        // 🔍 Buscar si ya existe una conversación
        $existing = DB::table('conversations')
            ->where('listing_id', $listing->id)
            ->where(function($query) use ($buyerId, $sellerId) {
                $query->where('buyer_id', $buyerId)->where('seller_id', $sellerId);
            })
            ->first();

        if ($existing) {
            return response()->json(['success' => true, 'data' => ['id' => $existing->id]]);
        }

        // 🆕 Crear una nueva
        $newId = DB::table('conversations')->insertGetId([
            'listing_id' => $listing->id,
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'created_at' => now()
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $newId]], 201);
    }
}
