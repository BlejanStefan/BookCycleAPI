<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);

        // 1. Consulta base unificando listings, books y las relaciones geográficas en cascada
        $query = DB::table('listings')
            ->join('books', 'listings.book_id', '=', 'books.id')
            ->join('municipalities', 'listings.municipality_id', '=', 'municipalities.id')
            ->join('provinces', 'municipalities.province_id', '=', 'provinces.id')
            ->join('communities', 'provinces.community_id', '=', 'communities.id')
            ->select(
                'listings.id as listing_id',
                'listings.price',
                'listings.condition',
                'listings.description',
                'listings.status',
                'listings.category_id',
                'listings.municipality_id',
                'books.id as book_id',
                'books.title as book_title',
                'books.year as book_year'
            );

        // 🔍 ESCENARIO 2: Barra de Búsqueda Global
        if ($request->filled('search')) {
            $search = $request->query('search');

            $query->where(function ($q) use ($search) {
                $q->where('books.title', 'LIKE', "%{$search}%")
                    ->orWhere('books.isbn13', 'LIKE', "%{$search}%")
                    ->orWhere('listings.description', 'LIKE', "%{$search}%")

                    ->orWhereExists(function ($subQuery) use ($search) {
                        $subQuery->select(DB::raw(1))
                            ->from('author_book')
                            ->join('authors', 'author_book.author_id', '=', 'authors.id')
                            ->whereColumn('author_book.book_id', 'books.id')
                            ->where('authors.name', 'LIKE', "%{$search}%");
                    })

                    ->orWhereExists(function ($subQuery) use ($search) {
                        $subQuery->select(DB::raw(1))
                            ->from('book_publisher')
                            ->join('publishers', 'book_publisher.publisher_id', '=', 'publishers.id')
                            ->whereColumn('book_publisher.book_id', 'books.id')
                            ->where('publishers.name', 'LIKE', "%{$search}%");
                    });
            });
        }
        // 🎛️ ESCENARIO 3: Búsqueda con Filtros Avanzados Específicos
        else {
            if ($request->filled('title')) {
                $query->where('books.title', 'LIKE', "%{$request->title}%");
            }

            if ($request->filled('year')) {
                $query->where('books.year', '=', $request->year);
            }

            if ($request->filled('condition')) {
                $query->where('listings.condition', '=', $request->condition);
            }

            if ($request->filled('price_max')) {
                $query->where('listings.price', '<=', $request->price_max);
            }

            if ($request->filled('category_id')) {
                $query->where('listings.category_id', '=', $request->category_id);
            }

            if ($request->filled('author')) {
                $query->whereExists(function ($subQuery) use ($request) {
                    $subQuery->select(DB::raw(1))
                        ->from('author_book')
                        ->join('authors', 'author_book.author_id', '=', 'authors.id')
                        ->whereColumn('author_book.book_id', 'books.id')
                        ->where('authors.name', 'LIKE', "%{$request->author}%");
                });
            }

            if ($request->filled('publisher')) {
                $query->whereExists(function ($subQuery) use ($request) {
                    $subQuery->select(DB::raw(1))
                        ->from('book_publisher')
                        ->join('publishers', 'book_publisher.publisher_id', '=', 'publishers.id')
                        ->whereColumn('book_publisher.book_id', 'books.id')
                        ->where('publishers.name', 'LIKE', "%{$request->publisher}%");
                });
            }

            // 🗺️ --- NUEVOS FILTROS GEOGRÁFICOS DE 3 NIVELES ---
            // Si filtra por municipio específico
            if ($request->filled('municipality_id')) {
                $query->where('listings.municipality_id', '=', $request->municipality_id);
            }
            // Si no eligió municipio pero sí una provincia entera
            elseif ($request->filled('province_id')) {
                $query->where('municipalities.province_id', '=', $request->province_id);
            }
            // Si no eligió ni municipio ni provincia pero sí una comunidad entera
            elseif ($request->filled('community_id')) {
                $query->where('provinces.community_id', '=', $request->community_id);
            }
        }

        // 🎲 ESCENARIO 1: Modo por defecto al azar si todo viene vacío
        if (!$request->filled('search') && !$request->anyFilled(['title', 'year', 'condition', 'price_max', 'category_id', 'author', 'publisher', 'community_id', 'province_id', 'municipality_id'])) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('listings.id', 'desc');
        }

        $listings = $query->limit($limit)->get();

        // 2. Formateamos la respuesta inyectando autores e imágenes
        $formattedListings = $listings->map(function ($listing) {
            $images = DB::table('listing_images')
                ->where('listing_id', $listing->listing_id)
                ->orderBy('order', 'asc')
                ->select('path', 'order')
                ->get()
                ->map(function ($img) {
                    // Si no empieza por http, significa que es local (ej: storage/listings/...) y le añadimos el dominio
                    $img->path = str_starts_with($img->path, 'http') ? $img->path : url($img->path);
                    return $img;
                });

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
    /**
     * Obtiene los detalles extendidos de un anuncio específico por su ID.
     */
    public function show($id)
    {
        // 1. Buscamos el anuncio cruzando la información básica del libro, categoría y usuario
        $listing = DB::table('listings')
            ->join('books', 'listings.book_id', '=', 'books.id')
            ->join('users', 'listings.user_id', '=', 'users.id')
            ->leftJoin('categories', 'listings.category_id', '=', 'categories.id')
            // Traemos la ubicación geográfica (Prioriza la del anuncio, si es NULL usa la del usuario)
            ->leftJoin('municipalities', function($join) {
                $join->on('municipalities.id', '=', DB::raw('COALESCE(listings.municipality_id, users.municipality_id)'));
            })
            ->leftJoin('provinces', 'municipalities.province_id', '=', 'provinces.id')
            ->where('listings.id', '=', $id)
            ->select(
                'listings.id as listing_id',
                'listings.price',
                'listings.condition',
                'listings.description',
                'listings.status',
                'listings.category_id',
                'books.id as book_id',
                'books.title as book_title',
                'books.year as book_year',
                'categories.name as category_name',
                'users.id as user_id',
                'users.username as user_name',
                'municipalities.id as municipality_id',
                'municipalities.name as municipality_name',
                'provinces.id as province_id',
                'provinces.community_id',
                'provinces.name as province_name'
            )

            ->first();
        // Si el anuncio no existe en la base de datos, respondemos con un 404
        if (!$listing) {
            return response()->json([
                'success' => false,
                'message' => 'El anuncio no existe o ha sido eliminado.'
            ], 404);
        }

        // 2. Extraemos las imágenes asociadas a este anuncio concreto
        $images = DB::table('listing_images')
            ->where('listing_id', $listing->listing_id)
            ->orderBy('order', 'asc')
            ->select('path', 'order')
            ->get()
            ->map(function ($img) {
                // Si no empieza por http, significa que es local (ej: storage/listings/...) y le añadimos el dominio
                $img->path = str_starts_with($img->path, 'http') ? $img->path : url($img->path);
                return $img;
            });

        // 3. Extraemos el/los autor/es del libro
        $authors = DB::table('author_book')
            ->join('authors', 'author_book.author_id', '=', 'authors.id')
            ->where('author_book.book_id', $listing->book_id)
            ->pluck('authors.name');

        // 4. Extraemos la editorial (Publisher) asociada al libro
        $publisher = DB::table('book_publisher')
            ->join('publishers', 'book_publisher.publisher_id', '=', 'publishers.id')
            ->where('book_publisher.book_id', $listing->book_id)
            ->value('publishers.name'); // Trae directamente el primer string o null

        // 5. Estructuramos la respuesta JSON para que encaje perfectamente con tu DetailView del Front
        $formattedData = [
            'listing_id'        => $listing->listing_id,
            'price'             => $listing->price,
            'condition'         => $listing->condition,
            'description'       => $listing->description,
            'status'            => $listing->status,
            'category_id'       => $listing->category_id,
            'community_id'      => $listing->community_id,
            'province_id'       => $listing->province_id,
            'municipality_id'   => $listing->municipality_id,
            'book_title'        => $listing->book_title,
            'book_year'         => $listing->book_year,
            'category_name'     => $listing->category_name,
            'publisher'         => $publisher,
            'municipality_name' => $listing->municipality_name,
            'province_name'     => $listing->province_name,
            'authors'           => $authors,
            'images'            => $images,
            'user' => [
                'id'   => $listing->user_id,
                'name' => $listing->user_name
            ]
        ];

        return response()->json([
            'success' => true,
            'data'    => $formattedData
        ], 200);
    }
    /**
     * Añade o elimina un anuncio de los favoritos del usuario autenticado.
     */
    public function toggleFavorite(Request $request, $id)
    {
        // En una API real usarías $request->user()->id, para pruebas usaremos el ID del usuario activo
        // Cambia este fallback por tu Auth::id() cuando implementes Sanctum/JWT
        $userId = $request->user()->id;
        // Verificamos si ya existe el registro en la tabla de favoritos
        $favorite = DB::table('favorites')
            ->where('user_id', $userId)
            ->where('listing_id', $id)
            ->first();

        if ($favorite) {
            // Si ya era favorito, lo quitamos (Desmarcar)
            DB::table('favorites')
                ->where('user_id', $userId)
                ->where('listing_id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'is_favorite' => false,
                'message' => 'Eliminado de tus favoritos.'
            ], 200);
        } else {
            // Si no existía, lo creamos (Marcar)
            DB::table('favorites')->insert([
                'user_id' => $userId,
                'listing_id' => $id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'is_favorite' => true,
                'message' => 'Añadido a tus favoritos con éxito.'
            ], 200);
        }
    }
    /**
     * Obtiene todos los anuncios marcados como favoritos por el usuario autenticado.
     */
    public function getFavorites(Request $request)
    {
        $userId = $request->user()->id;

        // 2. Construimos la consulta especificando detalladamente las tablas
        $listings = DB::table('favorites')
            ->join('listings', 'favorites.listing_id', '=', 'listings.id')
            ->join('books', 'listings.book_id', '=', 'books.id')
            ->leftJoin('municipalities', function($join) {
                $join->on('municipalities.id', '=', DB::raw('COALESCE(listings.municipality_id, books.id)'));
            })
            ->leftJoin('provinces', 'municipalities.province_id', '=', 'provinces.id')

            // 🚨 SOLUCIÓN AQUÍ: Especificamos 'favorites.user_id' de forma explícita para evitar duplicados de otras tablas
            ->where('favorites.user_id', '=', $userId)

            ->select(
                'listings.id as listing_id',
                'listings.price',
                'listings.condition',
                'listings.status',
                'books.title as book_title',
                'municipalities.name as municipality_name',
                'provinces.name as province_name'
            )
            ->get();

        // 3. Mapeamos imágenes y autores correspondientes
        foreach ($listings as $listing) {
            $listing->images = DB::table('listing_images')
                ->where('listing_id', $listing->listing_id)
                ->orderBy('order', 'asc')
                ->select('path')
                ->get()
                ->map(function ($img) {
                    $img->path = str_starts_with($img->path, 'http') ? $img->path : url($img->path);
                    return $img;
                });

            $bookId = DB::table('listings')->where('id', $listing->listing_id)->value('book_id');

            $listing->authors = DB::table('author_book')
                ->join('authors', 'author_book.author_id', '=', 'authors.id')
                ->where('author_book.book_id', $bookId)
                ->pluck('authors.name');
        }

        return response()->json([
            'success' => true,
            'data' => $listings
        ], 200);
    }
    public function store(Request $request)
    {
        \Log::info('Datos recibidos en request:', $request->all());
        \Log::info('Archivos recibidos:', $request->file('images') ?? ['vacio']);
        $request->validate([
            'price' => 'required|numeric',
            'condition' => 'required|string',
            'description' => 'nullable|string',
            'municipality_id' => 'required|integer',
            'category_id' => 'required|integer|exists:categories,id',
            'isbn' => 'required|string',
            'book_title' => 'required|string',
        ]);

        $userId = $request->user() ? $request->user()->id : 1;

        \DB::beginTransaction();
        try {
            $bookId = $request->book_id;

            // Si el libro no existía (Open Library falló o no devolvió datos) y el usuario lo rellenó a mano
            if (!$bookId) {
                // 1. Limpiamos el ISBN siguiendo la regla de tu BookService
                $cleanIsbn = preg_replace('/[^0-9]/', '', $request->isbn);

                // Buscamos si de casualidad se subió mientras tanto
                $bookId = \DB::table('books')->where('isbn13', $cleanIsbn)->value('id');

                if (!$bookId) {
                    $bookId = \DB::table('books')->insertGetId([
                        'isbn13' => $cleanIsbn,
                        'title' => $request->book_title,
                        'year' => $request->book_year,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Procesar Autores Manuales
                    if ($request->has('authors')) {
                        $authorsArray = is_array($request->authors) ? $request->authors : json_decode($request->authors, true);
                        if (!empty($authorsArray)) {
                            foreach ($authorsArray as $authorName) {
                                if (empty($authorName)) continue;
                                $author = \App\Models\Author::updateOrCreate(['name' => $authorName]);
                                \DB::table('author_book')->insert(['book_id' => $bookId, 'author_id' => $author->id]);
                            }
                        }
                    }

                    // Procesar Editoriales Manuales
                    if ($request->filled('publisher')) {
                        $publisher = \App\Models\Publisher::updateOrCreate(['name' => $request->publisher]);
                        \DB::table('book_publisher')->insert(['book_id' => $bookId, 'publisher_id' => $publisher->id]);
                    }
                }
            }

            // 2. Insertamos el anuncio en la base de datos
            $listingId = \DB::table('listings')->insertGetId([
                'user_id' => $userId,
                'book_id' => $bookId,
                'category_id' => $request->category_id,
                'price' => $request->price,
                'condition' => $request->condition,
                'description' => $request->description,
                'municipality_id' => $request->municipality_id,
                'status' => 'Disponible',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $files = [];
            foreach ($request->allFiles() as $key => $file) {
                if (strpos($key, 'image_') === 0 && $file->isValid()) {
                    $files[] = $file;
                }
            }
            if (!empty($files)) {
                \Log::info('Procesando ' . count($files) . ' archivos binarios detectados.');

                foreach ($files as $index => $image) {
                    $timestamp = time();
                    $extension = $image->getClientOriginalExtension();
                    $fileName = "listing_{$listingId}_{$timestamp}_{$index}.{$extension}";

                    $path = $image->storeAs('listings', $fileName, 'public');

                    if ($path) {
                        $publicPath = 'storage/' . $path;
                        \DB::table('listing_images')->insert([
                            'listing_id' => $listingId,
                            'path' => $publicPath,
                            'order' => $index,
                            'created_at' => now()
                        ]);
                    }
                }
            } else {
                // Si algo vuelve a fallar en la transferencia, este log te avisará de forma segura sin colgar el servidor
                \Log::warning('No se recibieron archivos binarios válidos bajo las claves image_X.');
            }

            \DB::commit();
            return response()->json(['success' => true, 'message' => '¡Anuncio publicado!', 'listing_id' => $listingId], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Fallo al procesar subida: ' . $e->getMessage()], 500);
        }
    }
    public function myListings(Request $request)
    {
        // 🔑 Obtenemos el ID del usuario autenticado gracias a Sanctum
        $userId = $request->user()->id;

        // 📚 Buscamos sus anuncios usando Query Builder, idéntico al estilo de tu método show()
        $listings = DB::table('listings')
            ->join('books', 'listings.book_id', '=', 'books.id')
            ->leftJoin('categories', 'listings.category_id', '=', 'categories.id')
            ->where('listings.user_id', '=', $userId)
            ->select(
                'listings.id as listing_id',
                'listings.price',
                'listings.condition',
                'listings.description',
                'listings.status',
                'books.title as book_title',
                'categories.name as category_name'
            )
            ->get();

        // 🖼️ Adjuntamos las imágenes convirtiendo las rutas relativas en URLs absolutas
        foreach ($listings as $listing) {
            $images = DB::table('listing_images')
                ->where('listing_id', '=', $listing->listing_id)
                ->orderBy('order', 'asc')
                ->select('path')
                ->get();

            // Mapeamos el resultado para inyectarle la IP del servidor automáticamente
            $listing->images = $images->map(function ($img) {
                return [
                    // asset() se encargará de concatenar el APP_URL de tu .env al path de la BD
                    'path' => asset($img->path)
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => $listings
        ]);
    }
    public function update(Request $request, $id)
    {
        // 1. Buscar el anuncio y validar propiedad
        $listing = DB::table('listings')->where('id', $id)->first();
        if (!$listing) {
            return response()->json(['success' => false, 'message' => 'Anuncio no encontrado'], 404);
        }

        // Seguridad: El usuario autenticado debe ser el dueño del anuncio
        if ($listing->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para editar este anuncio'], 403);
        }

        // 2. Validar los datos editables permitidos
        $request->validate([
            'price' => 'required|numeric|min:0',
            'condition' => 'required|string',
            'municipality_id' => 'required|integer',
            'category_id' => 'required|integer', // Permite cambiar la categoría
            'description' => 'nullable|string',
        ]);

        // 3. Actualizar la tabla 'listings' (El book_id NO se toca ni se incluye aquí)
        DB::table('listings')
            ->where('id', $id)
            ->update([
                'price' => $request->input('price'),
                'condition' => $request->input('condition'),
                'municipality_id' => $request->input('municipality_id'),
                'category_id' => $request->input('category_id'), // Guardamos la categoría modificada
                'description' => $request->input('description'),
                'updated_at' => now(),
            ]);

        // 4. LIMPIEZA Y SINCRONIZACIÓN DE FOTOS
        // Leemos las URLs absolutas de las imágenes que el usuario decidió CONSERVAR en React Native
        $keptImagesUrls = json_decode($request->input('kept_images', '[]'), true);
        $oldImages = DB::table('listing_images')->where('listing_id', $id)->get();

        foreach ($oldImages as $oldImg) {
            // Si la URL absoluta calculada no está en el array de las conservadas, se elimina
            if (!in_array(asset($oldImg->path), $keptImagesUrls)) {
                // Borrar archivo físico del almacenamiento (quitamos el prefijo 'storage/' de la BD)
                $diskPath = str_replace('storage/', '', $oldImg->path);
                Storage::disk('public')->delete($diskPath);

                // Borrar registro de la base de datos
                DB::table('listing_images')->where('id', $oldImg->id)->delete();
            }
        }

        // 5. SUBIR NUEVAS FOTOS AGREGADAS
        $listingId = $id;
        $timestamp = time();
        $index = 0;
        while ($request->hasFile("image_{$index}")) {
            $file = $request->file("image_{$index}");
            $extension = $file->getClientOriginalExtension();
            $fileName = "listing_{$listingId}_{$timestamp}_{$index}.{$extension}";
            // Guardamos el archivo físico en storage/app/public/listings
            $storedPath = $file->storeAs('listings', $fileName, 'public');

            // Construimos la ruta tal y como la guardas tú: 'storage/listings/nombre.jpg'
            $dbPath = 'storage/' . $storedPath;

            DB::table('listing_images')->insert([
                'listing_id' => $id,
                'path' => $dbPath,
                'order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $index++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Anuncio actualizado con éxito'
        ]);
    }
    public function destroy(Request $request, $id)
    {
        // 1. Buscar el anuncio
        $listing = DB::table('listings')->where('id', $id)->first();
        if (!$listing) {
            return response()->json(['success' => false, 'message' => 'Anuncio no encontrado'], 404);
        }

        // Seguridad: Solo el dueño puede destruirlo
        if ($listing->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        // 2. Buscar y borrar del disco todas las fotos asociadas
        $images = DB::table('listing_images')->where('listing_id', $id)->get();
        foreach ($images as $img) {
            $diskPath = str_replace('storage/', '', $img->path);
            Storage::disk('public')->delete($diskPath);
        }

        // 3. Eliminar registros en cascada en la BD
        DB::table('listing_images')->where('listing_id', $id)->delete();
        DB::table('listings')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anuncio eliminado correctamente de la plataforma'
        ]);
    }
}
