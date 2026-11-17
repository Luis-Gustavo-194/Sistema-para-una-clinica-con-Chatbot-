<?php

namespace Tests\Unit;

use App\Support\BaseInicial;
use PHPUnit\Framework\TestCase;

// El dump se importa en la BD que indique el entorno (en Railway se llama "railway"),
// sin las líneas que eligen la BD original o exigen privilegios de replicación
class BaseInicialTest extends TestCase
{
    public function test_quita_lo_que_impide_importar_el_dump_en_otra_base_de_datos(): void
    {
        $original = file_get_contents(dirname(__DIR__, 2).'/'.BaseInicial::ARCHIVO);
        $limpio = BaseInicial::limpiar($original);

        $this->assertStringContainsString('CREATE DATABASE', $original);
        $this->assertStringNotContainsString('CREATE DATABASE', $limpio);
        $this->assertDoesNotMatchRegularExpression('/^\s*USE\s+`/m', $limpio);
        $this->assertStringNotContainsString('GTID_PURGED', $limpio);
        $this->assertStringNotContainsString('SQL_LOG_BIN', $limpio);
    }

    public function test_conserva_las_tablas_y_los_datos(): void
    {
        $original = file_get_contents(dirname(__DIR__, 2).'/'.BaseInicial::ARCHIVO);
        $limpio = BaseInicial::limpiar($original);

        $this->assertSame(substr_count($original, 'CREATE TABLE'), substr_count($limpio, 'CREATE TABLE'));
        $this->assertSame(9, substr_count($limpio, 'CREATE TABLE'));
        foreach (['roles', 'usuarios', 'especialidades', 'medicos', 'horarios_medicos', 'pacientes', 'citas'] as $tabla) {
            $this->assertStringContainsString("INSERT INTO `{$tabla}`", $limpio);
        }
    }

    public function test_separa_el_dump_en_sentencias_completas(): void
    {
        $original = file_get_contents(dirname(__DIR__, 2).'/'.BaseInicial::ARCHIVO);
        $sentencias = BaseInicial::sentencias($original);

        $creates = array_values(array_filter($sentencias, fn ($s) => str_starts_with($s, 'CREATE TABLE')));
        $this->assertCount(9, $creates);
        $this->assertCount(substr_count($original, 'INSERT INTO'), array_filter($sentencias, fn ($s) => str_starts_with($s, 'INSERT INTO')));

        foreach ($sentencias as $sentencia) {
            $this->assertStringEndsWith(';', $sentencia);
            $this->assertStringStartsNotWith('--', $sentencia);
        }
        // Cada CREATE TABLE trae sus columnas y termina con el motor de la tabla
        $this->assertStringContainsString('ENGINE=InnoDB', $creates[0]);
    }

    public function test_respeta_sentencias_de_varias_lineas_y_omite_comentarios(): void
    {
        $sql = "-- comentario\nCREATE DATABASE x;\nUSE `x`;\n\nCREATE TABLE `t` (\n  `id` int\n) ENGINE=InnoDB;\nINSERT INTO `t` VALUES (1),(2);\n";

        $this->assertSame([
            "CREATE TABLE `t` (\n  `id` int\n) ENGINE=InnoDB;",
            'INSERT INTO `t` VALUES (1),(2);',
        ], BaseInicial::sentencias($sql));
    }
}
