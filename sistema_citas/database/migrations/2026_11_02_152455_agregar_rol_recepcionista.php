<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// HU03: rol Recepcionista con el ID fijo 4 (ver App\Models\Rol)
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(['id' => 4], ['nombre' => 'Recepcionista']);
    }

    public function down(): void
    {
        // usuarios.rol_id tiene ON DELETE RESTRICT: no se puede quitar el rol mientras alguien lo tenga
        if (DB::table('usuarios')->where('rol_id', 4)->exists()) {
            throw new RuntimeException('Hay usuarios con el rol Recepcionista. Asígnales otro rol antes de revertir esta migración.');
        }

        DB::table('roles')->where('id', 4)->delete();
    }
};
