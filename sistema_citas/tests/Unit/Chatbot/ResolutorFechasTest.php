<?php

namespace Tests\Unit\Chatbot;

use App\Chatbot\NormalizadorTexto;
use App\Chatbot\ResolutorFechas;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// HU09: fechas relativas y absolutas a ISO, tomando como "hoy" el lunes 16/11/2026
class ResolutorFechasTest extends TestCase
{
    private function resolver(string $texto): ?string
    {
        return ResolutorFechas::resolver(NormalizadorTexto::normalizar($texto), CarbonImmutable::create(2026, 11, 16, 15, 30));
    }

    public static function fechas(): array
    {
        return [
            'hoy' => ['¿Hay cita para hoy?', '2026-11-16'],
            'mañana' => ['quiero una cita mañana', '2026-11-17'],
            'Mañana con mayúscula y tilde' => ['Mañana a las 10', '2026-11-17'],
            'pasado mañana' => ['pasado mañana por favor', '2026-11-18'],
            'día de la semana' => ['el viernes', '2026-11-20'],
            'próximo lunes (hoy es lunes)' => ['el próximo lunes', '2026-11-23'],
            'miércoles sin tilde' => ['el miercoles a las 3', '2026-11-18'],
            'en N días' => ['en 3 días', '2026-11-19'],
            'dentro de N días en palabras' => ['dentro de dos dias', '2026-11-18'],
            'próxima semana' => ['la próxima semana', '2026-11-23'],
            'dd/mm' => ['el 20/11', '2026-11-20'],
            'dd-mm-aaaa' => ['20-11-2026', '2026-11-20'],
            'dd/mm/aa' => ['5/12/26', '2026-12-05'],
            'dd/mm que ya pasó → año siguiente' => ['el 5/1', '2027-01-05'],
            'día y mes en letras' => ['el 15 de diciembre', '2026-12-15'],
            'día y mes con año' => ['3 de marzo de 2027', '2027-03-03'],
            'mes que ya pasó → año siguiente' => ['10 de febrero', '2027-02-10'],
            'el día N de este mes' => ['el día 28', '2026-11-28'],
            'el N que ya pasó → mes siguiente' => ['el 10', '2026-12-10'],
        ];
    }

    #[DataProvider('fechas')]
    public function test_resuelve_fechas(string $texto, string $esperada): void
    {
        $this->assertSame($esperada, $this->resolver($texto));
    }

    public static function sinFecha(): array
    {
        return [
            'franja "en la mañana"' => ['en la mañana'],
            'franja "por la mañana"' => ['a las 10 de la mañana'],
            'fecha imposible' => ['el 31/02'],
            'número suelto' => ['1'],
            'hora sola' => ['a las 10:30'],
            'DNI' => ['11111111'],
            'sin fecha' => ['quiero una cita de cardiología'],
        ];
    }

    #[DataProvider('sinFecha')]
    public function test_no_inventa_fechas(string $texto): void
    {
        $this->assertNull($this->resolver($texto));
    }
}
