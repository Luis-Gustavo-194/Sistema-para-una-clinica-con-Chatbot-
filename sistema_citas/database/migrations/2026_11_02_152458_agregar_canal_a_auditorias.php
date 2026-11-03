<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU18: desde dónde se hizo la acción ('web' o 'chatbot')
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            if (! Schema::hasColumn('auditorias', 'canal')) {
                $table->string('canal', 20)->default('web')->after('direccion_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropColumn('canal');
        });
    }
};
