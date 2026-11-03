<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// HU12: canal de origen de cada reserva y bloqueo de doble reserva en la BD.
// MySQL no tiene índices parciales: la columna generada `turno_activo` vale 1 para las citas
// vigentes y NULL para las canceladas, y como un índice único admite varios NULL,
// una cita cancelada libera su turno.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            if (! Schema::hasColumn('citas', 'canal')) {
                $table->string('canal', 20)->default('web')->after('estado');
            }
            if (! Schema::hasColumn('citas', 'turno_activo')) {
                $table->unsignedTinyInteger('turno_activo')->nullable()
                    ->storedAs("CASE WHEN estado IS NULL OR estado <> 'Cancelada' THEN 1 END")
                    ->after('canal');
            }
        });

        if (! Schema::hasIndex('citas', 'citas_turno_unico')) {
            $duplicados = DB::table('citas')
                ->whereNotNull('turno_activo')
                ->select('medico_id', 'fecha', 'hora_inicio')
                ->groupBy('medico_id', 'fecha', 'hora_inicio')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicados > 0) {
                throw new RuntimeException("Hay {$duplicados} turno(s) con más de una cita vigente. Cancela las duplicadas antes de migrar.");
            }

            Schema::table('citas', fn (Blueprint $table) => $table->unique(['medico_id', 'fecha', 'hora_inicio', 'turno_activo'], 'citas_turno_unico'));
        }
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropUnique('citas_turno_unico');
            $table->dropColumn(['turno_activo', 'canal']);
        });
    }
};
