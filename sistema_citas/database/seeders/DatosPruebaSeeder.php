<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Rol;

// Datos de prueba para la BD MySQL sistema_citas_medicas.
// Ejecutar con: php artisan db:seed --class=DatosPruebaSeeder
//
// Es idempotente: cada registro se busca por su clave única y solo se inserta
// si no existe, así que puede ejecutarse varias veces sin duplicar nada.
// Las fechas de las citas son relativas al día en que se ejecuta.
class DatosPruebaSeeder extends Seeder
{
    // Contraseña de todos los usuarios creados por este seeder
    const CONTRASENA_PRUEBA = 'Prueba123*';

    public function run(): void
    {
        DB::transaction(function () {
            // ==========================================
            // 1. ROLES: el código usa 3 = Administrador
            // ==========================================
            $this->idOCrear('roles', ['id' => Rol::ADMINISTRADOR], ['nombre' => 'Administrador']);
            DB::table('usuarios')->where('correo', 'admin@clinica.com')->update(['rol_id' => Rol::ADMINISTRADOR]);

            // ==========================================
            // 2. ESPECIALIDADES (se conserva 'Cardiologia' existente)
            // ==========================================
            $especialidades = [
                'Cardiologia' => 'Diagnóstico y tratamiento de enfermedades del corazón.',
                'Pediatría' => 'Atención médica de bebés, niños y adolescentes.',
                'Medicina General' => 'Consulta general, chequeos y derivación a especialistas.',
                'Dermatología' => 'Enfermedades de la piel, cabello y uñas.',
                'Oftalmología' => 'Salud visual y enfermedades de los ojos.',
                'Odontología' => 'Salud bucal y dental.',
            ];
            $esp = [];
            foreach ($especialidades as $nombre => $descripcion) {
                $esp[$nombre] = $this->idOCrear('especialidades', ['nombre' => $nombre], ['descripcion' => $descripcion, 'estado' => 1]);
            }

            // ==========================================
            // 3. MÉDICOS (cada uno con su usuario rol Médico)
            // ==========================================
            $medicos = [
                // colegiatura, nombres, apellidos, correo, especialidad
                ['CMP-10001', 'Ana', 'Torres Vega', 'ana.torres@clinica.com', 'Cardiologia'],
                ['CMP-10002', 'Jorge', 'Salazar Mendoza', 'jorge.salazar@clinica.com', 'Pediatría'],
                ['CMP-10003', 'Rosa', 'Huamán Castillo', 'rosa.huaman@clinica.com', 'Medicina General'],
                ['CMP-10004', 'Pedro', 'Gutiérrez Ramos', 'pedro.gutierrez@clinica.com', 'Medicina General'],
                ['CMP-10005', 'Lucía', 'Fernández Paredes', 'lucia.fernandez@clinica.com', 'Dermatología'],
                ['CMP-10006', 'Miguel', 'Chávez Romero', 'miguel.chavez@clinica.com', 'Oftalmología'],
                ['CMP-10007', 'Carmen', 'Flores Díaz', 'carmen.flores@clinica.com', 'Odontología'],
            ];
            $med = [];
            foreach ($medicos as [$colegiatura, $nombres, $apellidos, $correo, $especialidad]) {
                $usuarioId = $this->usuario($correo, Rol::MEDICO);
                $med[$correo] = $this->idOCrear('medicos', ['colegiatura' => $colegiatura], [
                    'usuario_id' => $usuarioId,
                    'especialidad_id' => $esp[$especialidad],
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                ]);
            }
            // Médico existente (Dr. Carlos, usuario 2)
            $med['carlos.perez@clinica.com'] = DB::table('medicos')
                ->join('usuarios', 'usuarios.id', '=', 'medicos.usuario_id')
                ->where('usuarios.correo', 'carlos.perez@clinica.com')
                ->value('medicos.id');

            // Horario semanal: lunes (1) a viernes (5), 08:00 - 13:00, consultas de 30 min
            $consultorio = 101;
            foreach (array_filter($med) as $medicoId) {
                for ($dia = 1; $dia <= 5; $dia++) {
                    $this->idOCrear('horarios_medicos', ['medico_id' => $medicoId, 'dia_semana' => $dia], [
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '13:00:00',
                        'duracion_consulta_minutos' => 30,
                        'consultorio' => "C-{$consultorio}",
                    ]);
                }
                $consultorio++;
            }

            // ==========================================
            // 4. PACIENTES (cada uno con su usuario rol Paciente)
            // ==========================================
            $pacientes = [
                // dni, nombres, apellidos, teléfono, nacimiento, correo, usuario activo
                ['45871236', 'Ana María', 'Gonzales Ruiz', '987654321', '1985-03-14', 'ana.gonzales@pacientes.clinica.com', 1],
                ['70123456', 'José Luis', 'Ramírez Soto', '986543210', '1992-07-22', 'jose.ramirez@pacientes.clinica.com', 1],
                ['46589123', 'Carla', 'Mendoza Vílchez', '985432109', '1978-11-02', 'carla.mendoza@pacientes.clinica.com', 1],
                ['71234589', 'Diego Alonso', 'Paredes Lima', '984321098', '2015-05-30', 'diego.paredes@pacientes.clinica.com', 1],
                ['47896541', 'Elena', 'Castro Navarro', '983210987', '1964-01-18', 'elena.castro@pacientes.clinica.com', 1],
                ['72345678', 'Fernando', 'Rojas Quiroz', '982109876', '1999-09-09', 'fernando.rojas@pacientes.clinica.com', 1],
                ['48123697', 'Gabriela', 'Torres Sánchez', '981098765', '1988-12-25', 'gabriela.torres@pacientes.clinica.com', 0], // usuario inactivo
            ];
            $pac = [];
            foreach ($pacientes as [$dni, $nombres, $apellidos, $telefono, $nacimiento, $correo, $activo]) {
                $usuarioId = $this->usuario($correo, Rol::PACIENTE, $activo);
                $pac[$dni] = $this->idOCrear('pacientes', ['dni' => $dni], [
                    'usuario_id' => $usuarioId,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'telefono' => $telefono,
                    'fecha_nacimiento' => $nacimiento,
                ]);
            }
            // Paciente existente (Luis)
            $pac['70000001'] = DB::table('pacientes')->where('dni', '70000001')->value('id');

            // ==========================================
            // 5. CITAS (fechas relativas a hoy)
            // ==========================================
            $carlos = $med['carlos.perez@clinica.com'];
            $citas = [
                // dni paciente, médico, días desde hoy, hora inicio, estado
                // Dr. Carlos con mañana lleno (10:00 y 11:30): el chatbot debe saltar al día siguiente
                ['45871236', $carlos, 1, '10:00:00', 'Pendiente'],
                ['70123456', $carlos, 1, '11:30:00', 'Confirmada'],
                // Luis: citas vigentes para probar cancelar / reprogramar
                ['70000001', $carlos, 2, '10:00:00', 'Pendiente'],
                ['70000001', $med['ana.torres@clinica.com'], 4, '11:30:00', 'Pendiente'],
                ['70000001', $med['jorge.salazar@clinica.com'], 5, '10:00:00', 'Cancelada'], // no debe aparecer
                ['70000001', $med['rosa.huaman@clinica.com'], -7, '10:00:00', 'Atendida'],
                // Cancelada: no bloquea el turno de las 11:30 del Dr. Carlos en el día +2
                ['72345678', $carlos, 2, '11:30:00', 'Cancelada'],
                ['46589123', $carlos, 3, '11:30:00', 'Pendiente'],
                // Historial del Dr. Carlos
                ['71234589', $carlos, -3, '10:00:00', 'Atendida'],
                ['47896541', $carlos, -1, '11:30:00', 'No Asistio'],
                // Otros médicos
                ['71234589', $med['jorge.salazar@clinica.com'], 1, '10:00:00', 'Pendiente'],
                ['47896541', $med['rosa.huaman@clinica.com'], 1, '11:30:00', 'Pendiente'],
                ['72345678', $med['pedro.gutierrez@clinica.com'], 3, '10:00:00', 'Pendiente'],
                ['46589123', $med['lucia.fernandez@clinica.com'], 6, '10:00:00', 'Confirmada'],
            ];
            // Historias clínicas de ejemplo para las citas atendidas (por DNI del paciente)
            $historias = [
                '70000001' => [
                    'diagnostico' => 'Faringitis aguda.',
                    'tratamiento' => 'Reposo relativo e hidratación abundante por 5 días.',
                    'receta_medica' => "Paracetamol 500 mg cada 8 horas por 3 días.\nIbuprofeno 400 mg cada 12 horas si hay dolor.",
                    'observaciones' => 'Control si la fiebre persiste más de 72 horas.',
                ],
                '71234589' => [
                    'diagnostico' => 'Control cardiológico de rutina. Presión arterial y ritmo dentro de lo normal.',
                    'tratamiento' => 'Mantener actividad física moderada.',
                    'receta_medica' => null,
                    'observaciones' => 'Próximo control en 6 meses.',
                ],
            ];

            foreach ($citas as [$dni, $medicoId, $dias, $horaInicio, $estado]) {
                $citaId = $this->idOCrear('citas', [
                    'paciente_id' => $pac[$dni],
                    'medico_id' => $medicoId,
                    'fecha' => now()->addDays($dias)->toDateString(),
                    'hora_inicio' => $horaInicio,
                ], [
                    'hora_fin' => date('H:i:s', strtotime($horaInicio) + 30 * 60),
                    'estado' => $estado,
                ]);

                if ($estado === 'Atendida' && isset($historias[$dni])) {
                    $this->idOCrear('historias_clinicas', ['cita_id' => $citaId], $historias[$dni]);
                }
            }
        });
    }

    // Devuelve el id del registro que coincide con $clave; si no existe, lo inserta
    private function idOCrear(string $tabla, array $clave, array $datos): int
    {
        $id = DB::table($tabla)->where($clave)->value('id');

        return $id ?? DB::table($tabla)->insertGetId($clave + $datos);
    }

    private function usuario(string $correo, int $rolId, int $estado = 1): int
    {
        return $this->idOCrear('usuarios', ['correo' => $correo], [
            'rol_id' => $rolId,
            'contrasena' => Hash::make(self::CONTRASENA_PRUEBA),
            'estado' => $estado,
        ]);
    }
}
