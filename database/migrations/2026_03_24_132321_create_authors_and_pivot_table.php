<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla de Autores
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('ol_author_key')->unique()->nullable(); // ID de la API (ej: OL23919A)
            $table->string('name');
            $table->timestamps();
        });

        // Tabla Pivote para relación Muchos a Muchos (Libros <-> Autores)
        Schema::create('author_book', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->foreignId('author_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('author_book');
        Schema::dropIfExists('authors');
    }
};
