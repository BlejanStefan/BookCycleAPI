<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
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

            $book = Book::create([
                'isbn13' => $isbn,
                'ol_edition_key' => $this->extractEditionKey($data['url'] ?? ''),
                'title' => $data['title'] ?? 'Título desconocido',
                'year' => $this->extractYear($data['publish_date'] ?? null),
            ]);
            // 4. Procesar Autores
            if (isset($data['authors'])) {
                $authorIds = [];
                foreach ($data['authors'] as $authorData) {
                    $olKey = $this->extractEditionKey($authorData['url'] ?? '');

                    // Buscamos o creamos el autor por su clave de Open Library
                    $author = Author::updateOrCreate(
                        ['ol_author_key' => $olKey],
                        ['name' => $authorData['name']]
                    );

                    $authorIds[] = $author->id;
                }
                $book->authors()->syncWithoutDetaching($authorIds);
            }
            // 5. Procesar Publishers (Editoriales)
            if (isset($data['publishers'])) {
                $publisherIds = [];
                foreach ($data['publishers'] as $publisherName) {
                    // En Open Library los publishers vienen como texto
                    $publisher = Publisher::updateOrCreate(
                        ['name' => $publisherName['name'] ?? $publisherName]
                    );
                    $publisherIds[] = $publisher->id;
                }
                $book->publishers()->syncWithoutDetaching($publisherIds);
            }

            return $book->load(['authors', 'publishers']);
        }

        return null; // No encontrado
    }

    private function extractEditionKey(string $url): ?string
    {
        // Busca el patrón OLxxxxM en la URL
        if (preg_match('/(OL\d+[AM])/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractYear($dateString): ?int
    {
        if (!$dateString) return null;
        // Intenta sacar los últimos 4 dígitos (el año)
        if (preg_match('/\d{4}/', $dateString, $matches)) {
            return (int)$matches[0];
        }
        return null;
    }
}
