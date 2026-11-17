<?php

use App\Support\BaseInicial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Las tablas base (usuarios, pacientes, citas...) nacen del dump y las migraciones siguientes las modifican.
// En una base de datos MySQL vacía (p. ej. el primer despliegue en Railway) se importa el dump antes que todo;
// si las tablas ya existen (BD local) o no es MySQL (pruebas con SQLite), no hace nada.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || Schema::hasTable('usuarios')) {
            return;
        }

        foreach (BaseInicial::sentencias(file_get_contents(base_path(BaseInicial::ARCHIVO))) as $sentencia) {
            DB::unprepared($sentencia);
        }
    }

    public function down(): void
    {
        // Los datos importados no se borran al revertir
    }
};
