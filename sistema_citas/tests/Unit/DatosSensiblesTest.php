<?php

namespace Tests\Unit;

use App\Support\DatosSensibles;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

// HU18: datos personales parcialmente ocultos en la bitácora
class DatosSensiblesTest extends TestCase
{
    #[TestWith(['Se registró (DNI 76543210)', 'Se registró (DNI 76****10)'])]
    #[TestWith(['Correo rosa.quispe@correo.com', 'Correo r***@correo.com'])]
    #[TestWith(['Teléfono 999888777', 'Teléfono 9*****777'])]
    #[TestWith(['DNI 76543210, tel. 987654321', 'DNI 76****10, tel. 9*****321'])]
    public function test_enmascara_dni_correo_y_telefono(string $texto, string $esperado): void
    {
        $this->assertSame($esperado, DatosSensibles::enmascarar($texto));
    }

    #[TestWith(['Agendó la cita #12 desde su panel'])]
    #[TestWith(['Código 1234567'])]
    #[TestWith(['Número 123456789012'])]
    public function test_no_toca_textos_sin_datos_sensibles(string $texto): void
    {
        $this->assertSame($texto, DatosSensibles::enmascarar($texto));
    }
}
