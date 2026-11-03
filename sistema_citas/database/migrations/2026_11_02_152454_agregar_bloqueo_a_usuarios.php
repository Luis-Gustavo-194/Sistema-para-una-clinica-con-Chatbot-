<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU01: contador de intentos fallidos y bloqueo temporal de la cuenta
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (! Schema::hasColumn('usuarios', 'intentos_fallidos')) {
                $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('estado');
            }
            if (! Schema::hasColumn('usuarios', 'bloqueado_hasta')) {
                $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['intentos_fallidos', 'bloqueado_hasta']);
        });
    }
};
