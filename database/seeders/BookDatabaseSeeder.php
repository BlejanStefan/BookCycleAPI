<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\BookService;

class BookDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Instanciamos tu servicio para aprovechar la lógica que ya programaste
        $bookService = new BookService();

        // 🚀 Array con más de 100 ISBNs de libros populares y comerciales
        $isbns = [
            // Ciencia Ficción y Fantasía
            '9788445073735', '9788498387087', '9788498387094', '9788498387100', '9788498387117',
            '9788498387124', '9788498387131', '9788498387148', '9788445071724', '9788445000663',
            '9788466657662', '9788466658904', '9788466659383', '9788499082479', '9788415835639',
            '9788408064435', '9788496208629', '9788496208636', '9788445074879', '9788417347291',

            // Distopías y Clásicos Modernos
            '9788499890944', '9788423351718', '9788420651330', '9788497931014', '9788497593793',
            '9788466311229', '9788432241031', '9788497592420', '9788420674209', '9788491392637',
            '9788497593069', '9788401352348', '9788433920089', '9788426105158', '9788401337192',

            // Misterio, Thriller y Novela Negra
            '9788491291886', '9788467053357', '9788408235477', '9788439739418', '9788416550609',
            '9788423355617', '9788416224029', '9788439722335', '9788490627587', '9788408223191',
            '9788417001469', '9788416358489', '9788466352932', '9788417708542', '9788408243304',

            // Juvenil, Histórica y Desarrollo Personal
            '9788424115166', '9788408004349', '9788499181516', '9788420483535', '9788467036732',
            '9788490607725', '9788492915125', '9788416508938', '9788417605704', '9788496581173',
            '9788415678229', '9788418051005', '9788416588824', '9788401022715', '9788492414116',

            // Más títulos de alta disponibilidad en Open Library
            '9780439139601', '9780439064873', '9780590353427', '9780743273565', '9780345391803',
            '9780441172719', '9780316769488', '9780061120084', '9780451524935', '9781400032716',
            '9780385490818', '9780141439518', '9780142437230', '9780451526342', '9780307474728',
            '9780060850524', '9780307387899', '9780307277671', '9780735211292', '9781501110368',
            '9780399590504', '9780525559474', '9780316055437', '9780316166614', '9780316015844',
            '9781451673319', '9780743247542', '9781501111198', '9781982181284', '9781501171345'
        ];

        $this->command->info('=== Iniciando el sembrado de libros (100 ejemplares) ===');

        $contador = 0;

        foreach ($isbns as $isbn) {
            try {
                // Llamamos a tu método getByIsbn
                $book = $bookService->getByIsbn($isbn);

                if ($book) {
                    $contador++;
                    $this->command->info("({$contador}) Insertado con éxito: \"{$book->title}\"");
                } else {
                    $this->command->warn("No se encontró información para el ISBN: {$isbn}");
                }
            } catch (\Exception $e) {
                $this->command->error("Error procesando el ISBN {$isbn}: " . $e->getMessage());
            }

            // 💡 Truco importante: Añadimos una pausa de medio segundo entre peticiones
            // para evitar que la API de Open Library nos bloquee por exceso de tráfico (Rate Limit).
            usleep(500000);
        }

        $this->command->info("=== Proceso terminado. Se han añadido {$contador} libros reales ===");
    }
}
