<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BookService;

class BookController extends Controller
{
    protected $bookService;

    // Inyectamos de forma limpia tu BookService existente
    public function __construct(BookService $bookService)
    {
        $this->bookService = $bookService;
    }

    public function checkByIsbn($isbn)
    {
        // Ejecutamos tu método existente
        $book = $this->bookService->getByIsbn($isbn);

        if ($book) {
            return response()->json([
                'success' => true,
                'data' => $book // Esto ya incluye relaciones gracias a tu return $book->load(...)
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Libro no encontrado en Open Library. Rellena los datos manualmente.'
        ], 404);
    }
}
