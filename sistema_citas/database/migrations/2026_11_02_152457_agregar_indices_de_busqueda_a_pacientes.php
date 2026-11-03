<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU06: la búsqueda de recepción filtra por prefijo de apellidos y nombres
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            if (! Schema::hasIndex('pacientes', 'pacientes_apellidos_nombres_index')) {
                $table->index(['apellidos', 'nombres'], 'pacientes_apellidos_nombres_index');
            }
            if (! Schema::hasIndex('pacientes', 'pacientes_nombres_index')) {
                $table->index('nombres', 'pacientes_nombres_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropIndex('pacientes_apellidos_nombres_index');
            $table->dropIndex('pacientes_nombres_index');
        });
    }
};
