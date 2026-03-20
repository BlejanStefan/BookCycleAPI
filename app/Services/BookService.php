<?php
namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Facades\Http;

class BookService
{
    public function getByIsbn(string $isbn)
    {
        // 1. Limpiar el ISBN (solo números)
        $isbn = preg_replace('/[^0-9]/', '', $isbn);

        // 2. Buscar si ya existe en nuestra DB (referencia isbn13 según ER)
        $book = Book::where('isbn13', $isbn)->first();
        if ($book) {
            return $book;
        }

        // 3. Consultar la API de Open Library
        $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";
        $response = Http::get($url);

        if ($response->successful() && isset($response->json()["ISBN:{$isbn}"])) {
            $data = $response->json()["ISBN:{$isbn}"];

            return Book::create([
                'isbn13'         => $isbn,
                'ol_edition_key' => $this->extractEditionKey($data['url'] ?? ''),
                'title'          => $data['title'] ?? 'Título desconocido',
                'year'           => $this->extractYear($data['publish_date'] ?? null),
            ]);
        }

        return null; // No encontrado
    }

    private function extractEditionKey(string $url): ?string
    {
        // Busca el patrón OLxxxxM en la URL
        if (preg_match('/(OL\d+M)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractYear($dateString): ?int
    {
        if (!$dateString) return null;
        // Intenta sacar los últimos 4 dígitos (el año)
        if (preg_match('/\d{4}/', $dateString, $matches)) {
            return (int) $matches[0];
        }
        return null;
    }
}
