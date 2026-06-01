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
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();

            // Relación con la tabla de usuarios
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade'); // Si se borra el usuario, se limpian sus favoritos

            // Relación con la tabla de anuncios
            $table->foreignId('listing_id')
                ->constrained('listings')
                ->onDelete('cascade'); // Si se borra el anuncio, se quita de favoritos

            // Evitamos duplicados: un usuario no puede marcar dos veces el mismo anuncio
            $table->unique(['user_id', 'listing_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
