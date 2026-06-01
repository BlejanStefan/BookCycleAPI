<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Obtiene todas las categorías disponibles para los libros.
     */
    public function index()
    {
        // Traemos el ID y el Nombre de tu tabla según tu diagrama ER
        $categories = DB::table('categories')
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        // Devolvemos la respuesta para que el geoService del Front la reciba directamente
        return response()->json($categories, 200);
    }
}
