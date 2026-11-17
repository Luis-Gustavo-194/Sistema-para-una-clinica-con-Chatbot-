<?php

namespace Tests\Unit\Chatbot;

use App\Chatbot\NormalizadorTexto;
use PHPUnit\Framework\TestCase;

// HU09: minúsculas, sin tildes ni signos y con un solo espacio; se conservan los separadores de horas y fechas
class NormalizadorTextoTest extends TestCase
{
    public function test_quita_tildes_mayusculas_y_signos(): void
    {
        $this->assertSame('manana a las 10:30 cardiologia', NormalizadorTexto::normalizar('¡Mañana a las 10:30,   Cardiología!'));
        $this->assertSame('quiero cita con la dra rios', NormalizadorTexto::normalizar('¿Quiero cita con la Dra. Ríos?'));
    }

    public function test_conserva_separadores_de_fechas(): void
    {
        $this->assertSame('el 20/11 o el 21-11', NormalizadorTexto::normalizar('El 20/11 o el 21-11.'));
    }
}
