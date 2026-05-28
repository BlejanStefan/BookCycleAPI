<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    /**
     * Obtiene un listado aleatorio de anuncios para la pantalla principal.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);

        // Quitamos el WHERE del status para que devuelva datos sí o sí
        $listings = DB::table('listings')
            ->join('books', 'listings.book_id', '=', 'books.id')
            ->select(
                'listings.id as listing_id',
                'listings.price',
                'listings.condition',
                'listings.description',
                'listings.status',
                'books.id as book_id',
                'books.title as book_title',
                'books.year as book_year'
            )
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        $formattedListings = $listings->map(function ($listing) {

            $images = DB::table('listing_images')
                ->where('listing_id', $listing->listing_id)
                ->orderBy('order', 'asc')
                ->select('path', 'order')
                ->get();

            $authors = DB::table('author_book')
                ->join('authors', 'author_book.author_id', '=', 'authors.id')
                ->where('author_book.book_id', $listing->book_id)
                ->pluck('authors.name');

            return [
                'listing_id'  => $listing->listing_id,
                'price'       => $listing->price,
                'condition'   => $listing->condition,
                'description' => $listing->description,
                'status'      => $listing->status,
                'book_title'  => $listing->book_title,
                'book_year'   => $listing->book_year,
                'authors'     => $authors,
                'images'      => $images
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $formattedListings
        ], 200);
    }
}
