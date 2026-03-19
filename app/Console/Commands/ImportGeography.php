<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use App\Models\Province;
use App\Models\Municipality;
use App\Models\Community;
#[Signature('app:import-geography')]
#[Description('Command description')]
class ImportGeography extends Command
{
    protected $signature = 'import:geography';
    protected $description = 'Importa provincias y municipios desde la API del INE';
    /**
     * Execute the console command.
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $this->info('Conectando con GeoAPI para obtener Comunidades...');

        // Tu URL con la API Key
        $url = 'https://apiv1.geoapi.es/comunidades?type=JSON&key=6cecaa7b077822e004100ee146da8db7be7686b024c8a1573ef78af6e6ffe754';

        $response = Http::get($url);

        if ($response->successful()) {
            // La API devuelve un objeto con una propiedad "data" que es el array
            $comunidades = $response->json('data');

            $this->info('Importando comunidades autónomas...');

            $this->withProgressBar($comunidades, function ($item) {
                // Estructura de la API: {"CCOM": "01", "COM": "Andalucía"}
                Community::updateOrCreate(
                    ['id' => (int)$item['CCOM']], // Usamos el código oficial como ID
                    ['name' => $item['COM']]
                );
            });

            $this->newLine();
            $this->info('¡Comunidades importadas correctamente!');
        } else {
            $this->error('Error al conectar con GeoAPI. Revisa la Key o la conexión.');
        }
    }
}
