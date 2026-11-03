<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Seguro de salud del paciente y fecha en que aceptó el tratamiento de datos (Ley 29733)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            if (! Schema::hasColumn('pacientes', 'seguro')) {
                $table->string('seguro', 20)->nullable()->after('fecha_nacimiento');
            }
            if (! Schema::hasColumn('pacientes', 'consentimiento_datos_at')) {
                $table->timestamp('consentimiento_datos_at')->nullable()->after('seguro');
            }
        });

        // El dump ya trae el índice único `dni`; se crea solo si falta
        if (! Schema::hasIndex('pacientes', ['dni'], 'unique')) {
            Schema::table('pacientes', fn (Blueprint $table) => $table->unique('dni'));
        }
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn(['seguro', 'consentimiento_datos_at']);
        });
    }
};
