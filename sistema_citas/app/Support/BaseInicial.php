<?php

namespace App\Support;

// Prepara Dump20261026.sql (tablas base y datos de ejemplo) para importarlo en otra base de datos,
// p. ej. la de Railway: quita las líneas que crean/eligen la BD original o que exigen privilegios
// de replicación (SQL_LOG_BIN, GTID_PURGED). Lo usa la migración importar_base_inicial.
class BaseInicial
{
    const ARCHIVO = 'Dump20261026.sql';

    public static function limpiar(string $sql): string
    {
        $lineas = preg_split('/\R/', $sql);

        $conservadas = array_filter($lineas, fn (string $linea) => ! preg_match('/^\s*(?:CREATE DATABASE\b|USE\s+`)|SQL_LOG_BIN|GTID_PURGED/i', $linea));

        return implode("\n", $conservadas);
    }

    // Sentencias del dump limpio, una por elemento, para ejecutarlas por separado: si una falla,
    // la migración se detiene con el error en vez de dejar la importación a medias sin avisar.
    // En mysqldump cada sentencia termina con ";" al final de una línea (los INSERT van en una sola línea).
    public static function sentencias(string $sql): array
    {
        $sentencias = [];
        $actual = '';

        foreach (preg_split('/\R/', self::limpiar($sql)) as $linea) {
            $recortada = trim($linea);
            if ($actual === '' && ($recortada === '' || str_starts_with($recortada, '--'))) {
                continue;
            }

            $actual .= $linea."\n";
            if (str_ends_with($recortada, ';')) {
                $sentencias[] = trim($actual);
                $actual = '';
            }
        }

        return $sentencias;
    }
}
