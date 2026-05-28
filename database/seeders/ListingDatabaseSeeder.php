<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Obtener los libros insertados
        $books = Book::all();

        if ($books->isEmpty()) {
            $this->command->error('No hay libros en la base de datos para asociar los anuncios.');
            return;
        }

        // 2. Obtener un ID de categoría y municipio reales de tu DB
        $categoryId = DB::table('categories')->value('id');
        $municipalityId = DB::table('municipalities')->value('id');

        if (!$categoryId || !$municipalityId) {
            $this->command->error('Error: Las tablas "categories" o "municipalities" están vacías.');
            return;
        }

        // 3. Obtener usuarios (o crear uno de respaldo si está vacía)
        if (User::count() === 0) {
            User::create([
                'name' => 'Usuario Demo',
                'email' => 'demo@bookcycle.com',
                'password' => bcrypt('password'),
            ]);
        }
        $users = User::all();

        // Opciones EXACTAS de tu ENUM
        $conditions = ['Nuevo', 'Casi nuevo', 'Usado', 'Gastado'];

        $this->command->info('=== Generando registros en LISTINGS y LISTING_IMAGES ===');

        $contadorAnuncios = 0;
        $contadorImagenes = 0;

        foreach ($books as $book) {
            $numListings = rand(0, 2);

            for ($i = 0; $i < $numListings; $i++) {
                $condition = $conditions[array_rand($conditions)];

                $descripciones = [
                    'Nuevo'      => "Libro completamente nuevo, sin abrir ni leer. Ideal para regalo.",
                    'Casi nuevo' => "Edición impecable, leído una vez y guardado en estantería sin marcas.",
                    'Usado'      => "Tiene esquinas ligeramente desgastadas por el uso común, pero el interior está perfecto.",
                    'Gastado'    => "Libro antiguo con hojas amarillentas y marcas de uso. Ideal para lectura económica."
                ];

                // 🚀 INSERTAMOS EL ANUNCIO Y CAPTURAMOS SU ID GENERADO
                $listingId = DB::table('listings')->insertGetId([
                    'book_id'         => $book->id,
                    'user_id'         => $users->random()->id,
                    'category_id'     => $categoryId,
                    'municipality_id' => $municipalityId,
                    'price'           => rand(5, 20),
                    'condition'       => $condition,
                    'status'          => 'Disponible',
                    'description'     => $descripciones[$condition],
                    'created_at'      => now()->subDays(rand(1, 10)),
                    'updated_at'      => now(),
                ]);

                $contadorAnuncios++;

                // 🚀 INSERTAMOS LAS IMÁGENES ASOCIADAS EN 'listing_images' (De 1 a 3 fotos por anuncio)
                $numImages = rand(1, 3);
                for ($imgOrder = 0; $imgOrder < $numImages; $imgOrder++) {

                    // Usamos URLs simuladas de Picsum. En tu frontend, el componente <Image source={{uri: item.path}} /> las leerá perfectamente
                    $fakeImagePath = "https://picsum.photos/seed/listing_{$listingId}_{$imgOrder}/400/600";

                    DB::table('listing_images')->insert([
                        'listing_id' => $listingId,
                        'path'       => $fakeImagePath, // Cumple con tu columna string 'path'
                        'order'      => $imgOrder,       // Guarda la posición (0, 1, 2...)
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $contadorImagenes++;
                }
            }
        }

        $this->command->info("=== Seeder finalizado con éxito ===");
        $this->command->info("✔ Anuncios creados en 'listings': {$contadorAnuncios}");
        $this->command->info("✔ Fotos asociadas en 'listing_images': {$contadorImagenes}");
    }
}
