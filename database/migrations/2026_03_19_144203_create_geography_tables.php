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
        // 1. Comunidades Autónomas
        Schema::create('communities', function (Blueprint $table) {
            $table->id(); // ID oficial (ej: 01, 02...)
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 2. Provincias
        Schema::create('provinces', function (Blueprint $table) {
            $table->id(); // ID oficial (ej: 04 para Almería, 28 para Madrid)
            $table->string('name')->unique();
            $table->foreignId('community_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // 3. Municipios
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('province_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geography_tables');
    }
};
