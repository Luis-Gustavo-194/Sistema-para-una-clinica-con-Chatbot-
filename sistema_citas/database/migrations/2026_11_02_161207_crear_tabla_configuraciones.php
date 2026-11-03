<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Datos de la clínica que el Administrador edita (nombre, teléfonos, dirección...).
// Lo que no esté aquí sale de config/clinica.php.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            Schema::create('configuraciones', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 100)->unique();
                $table->text('valor')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
