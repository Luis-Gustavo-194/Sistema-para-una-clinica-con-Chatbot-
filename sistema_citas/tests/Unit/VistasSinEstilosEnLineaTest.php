<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

// El CSS vive en resources/css y el JavaScript en resources/js:
// ninguna vista Blade debe traer <style>, <script> ni manejadores on* en línea.
class VistasSinEstilosEnLineaTest extends TestCase
{
    public function test_las_vistas_no_tienen_css_ni_js_en_linea(): void
    {
        $directorio = dirname(__DIR__, 2) . '/resources/views';
        $infracciones = [];

        $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));
        foreach ($archivos as $archivo) {
            if (!str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }
            $contenido = file_get_contents($archivo->getPathname());
            foreach (['/<style\b/i', '/<script\b/i', '/\son(click|submit|change|keypress|load)\s*=/i', '/\sstyle\s*=/i'] as $patron) {
                if (preg_match($patron, $contenido, $coincidencia)) {
                    $infracciones[] = str_replace($directorio, 'views', $archivo->getPathname()) . ' → ' . trim($coincidencia[0]);
                }
            }
        }

        $this->assertSame([], $infracciones, "Hay CSS/JS en línea en las vistas:\n" . implode("\n", $infracciones));
    }
}
