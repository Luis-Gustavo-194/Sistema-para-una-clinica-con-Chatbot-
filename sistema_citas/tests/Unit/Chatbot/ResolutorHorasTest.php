<?php

namespace Tests\Unit\Chatbot;

use App\Chatbot\NormalizadorTexto;
use App\Chatbot\ResolutorHoras;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// HU09: horas escritas de distintas formas a H:i
class ResolutorHorasTest extends TestCase
{
    private function resolver(string $texto): ?string
    {
        return ResolutorHoras::resolver(NormalizadorTexto::normalizar($texto));
    }

    public static function horas(): array
    {
        return [
            'hh:mm' => ['mañana 10:30', '10:30'],
            'hh:mm pm' => ['a las 3:15 pm', '15:15'],
            'h pm' => ['3 pm', '15:00'],
            'h am pegado' => ['10am', '10:00'],
            'p.m. con puntos' => ['4 p.m.', '16:00'],
            'a las h (mañana)' => ['a las 10', '10:00'],
            'a las 4 sin calificador → tarde' => ['a las 4', '16:00'],
            'y media' => ['a las 10 y media', '10:30'],
            'y cuarto' => ['a las 9 y cuarto', '09:15'],
            'en palabras' => ['a las once', '11:00'],
            'a la una' => ['a la una', '13:00'],
            'de la mañana' => ['11 de la mañana', '11:00'],
            'de la tarde' => ['a las 5 de la tarde', '17:00'],
            '12 am' => ['12 am', '00:00'],
            'mediodía' => ['al mediodía', '12:00'],
        ];
    }

    #[DataProvider('horas')]
    public function test_resuelve_horas(string $texto, string $esperada): void
    {
        $this->assertSame($esperada, $this->resolver($texto));
    }

    public static function sinHora(): array
    {
        return [
            'número suelto (valor de un botón)' => ['1'],
            'DNI' => ['11111111'],
            'fecha' => ['el 20/11'],
            'día del mes' => ['el 20'],
            'hora imposible' => ['25:00'],
            'sin hora' => ['quiero una cita mañana'],
        ];
    }

    #[DataProvider('sinHora')]
    public function test_no_inventa_horas(string $texto): void
    {
        $this->assertNull($this->resolver($texto));
    }
}
