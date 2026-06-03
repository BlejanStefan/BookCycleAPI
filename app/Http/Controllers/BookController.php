<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\BookService;

class BookController extends Controller
{
    protected $bookService;

    public function __construct(BookService $bookService)
    {
        $this->bookService = $bookService;
    }
    /**
     * Consulta la información de un libro a través de un servicio externo
     * utilizando su código ISBN.
     * * @param  string $isbn El código ISBN del libro a buscar.
     * @return JsonResponse
     */
    public function checkByIsbn($isbn)
    {
        $book = $this->bookService->getByIsbn($isbn);

        if ($book) {
            return response()->json([
                'success' => true,
                'data' => $book
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Libro no encontrado en Open Library. Rellena los datos manualmente.'
        ], 404);
    }
}
