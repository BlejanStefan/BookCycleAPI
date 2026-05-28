<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Community;
use App\Models\Province;
use App\Models\Municipality;

class ImportGeography extends Command
{
    protected $signature = 'import:geography';
    protected $description = 'Importa la geografía española con IDs únicos para evitar duplicados';

    public function handle()
    {
        $apikey = "6cecaa7b077822e004100ee146da8db7be7686b024c8a1573ef78af6e6ffe754";

        $this->info('Iniciando limpieza e importación... (Esto evitará los 24.000 registros)');

        $resCom = Http::get("https://apiv1.geoapi.es/comunidades?type=JSON&key={$apikey}");
        if (!$resCom->successful()) return $this->error('Error API Comunidades');

        $comunidades = $resCom->json('data');

        foreach ($comunidades as $c) {
            $comunidad = Community::updateOrCreate(
                ['id' => (int)$c['CCOM']],
                ['name' => $c['COM']]
            );
            $this->info("➤ {$comunidad->name}");

            $resProv = Http::get("https://apiv1.geoapi.es/provincias?CCOM={$c['CCOM']}&type=JSON&key={$apikey}");

            if ($resProv->successful()) {
                foreach ($resProv->json('data') as $p) {
                    $provincia = Province::updateOrCreate(
                        ['id' => (int)$p['CPRO']],
                        ['name' => $p['PRO'], 'community_id' => $comunidad->id]
                    );

                    $cpro_api = str_pad($p['CPRO'], 2, '0', STR_PAD_LEFT);
                    $resMun = Http::get("https://apiv1.geoapi.es/municipios?CPRO={$cpro_api}&type=JSON&key={$apikey}");

                    if ($resMun->successful()) {
                        foreach ($resMun->json('data') as $m) {
                            // GENERAMOS EL ID ÚNICO (CPRO + CMUM)
                            // Esto evita que se dupliquen si el script se relanza
                            $idReal = (int)($m['CPRO'] . $m['CMUM']);

                            Municipality::updateOrCreate(
                                ['id' => $idReal],
                                [
                                    'name' => $m['DMUN50'],
                                    'province_id' => $provincia->id
                                ]
                            );
                        }
                    }
                }
            }
        }
        $this->info('¡Hecho! Ahora deberías tener exactamente 8.131 municipios.');
    }
}
