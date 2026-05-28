<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{


    public function run(): void
    {
        $categories = [
            'Novela', 'Ciencia Ficción', 'Fantasía', 'Terror',
            'Romance', 'Aventura', 'Biografía', 'Historia',
            'Infantil', 'Juvenil', 'Texto / Académico', 'Cómic / Manga'
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category,
                'slug' => Str::slug($category),
            ]);
        }
    }
}
