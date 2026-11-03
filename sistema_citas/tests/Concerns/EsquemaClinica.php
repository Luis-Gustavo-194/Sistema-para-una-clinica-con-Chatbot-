<?php

namespace Tests\Concerns;

use App\Models\Rol;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

// La BD real (MySQL sistema_citas_medicas) no tiene migraciones para sus tablas.
// Este trait recrea ese esquema en la SQLite en memoria de las pruebas (phpunit.xml)
// y ofrece ayudantes para insertar datos.
trait EsquemaClinica
{
    protected function crearEsquemaClinica(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 50)->unique();
            $t->timestamps();
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->id();
            $t->integer('rol_id');
            $t->string('correo', 150)->unique();
            $t->string('contrasena');
            $t->boolean('estado')->nullable()->default(1);
            $t->unsignedTinyInteger('intentos_fallidos')->default(0);
            $t->timestamp('bloqueado_hasta')->nullable();
            $t->timestamps();
        });
        Schema::create('especialidades', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 100)->unique();
            $t->text('descripcion')->nullable();
            $t->boolean('estado')->nullable()->default(1);
            $t->timestamps();
        });
        Schema::create('medicos', function (Blueprint $t) {
            $t->id();
            $t->integer('usuario_id')->unique();
            $t->integer('especialidad_id');
            $t->string('colegiatura', 50)->unique();
            $t->string('nombres', 100);
            $t->string('apellidos', 100);
            $t->timestamps();
        });
        Schema::create('horarios_medicos', function (Blueprint $t) {
            $t->id();
            $t->integer('medico_id');
            $t->tinyInteger('dia_semana');
            $t->time('hora_inicio');
            $t->time('hora_fin');
            $t->integer('duracion_consulta_minutos')->nullable()->default(30);
            $t->string('consultorio', 50)->nullable();
            $t->timestamps();
        });
        Schema::create('pacientes', function (Blueprint $t) {
            $t->id();
            $t->integer('usuario_id')->nullable(); // NOT NULL en MySQL; aquí opcional para simplificar
            $t->string('dni', 15)->unique();
            $t->string('nombres', 100);
            $t->string('apellidos', 100);
            $t->string('telefono', 20)->nullable();
            $t->date('fecha_nacimiento')->nullable();
            $t->string('seguro', 20)->nullable();
            $t->timestamp('consentimiento_datos_at')->nullable();
            $t->timestamps();
            $t->index(['apellidos', 'nombres']);
            $t->index('nombres');
        });
        Schema::create('citas', function (Blueprint $t) {
            $t->id();
            $t->integer('paciente_id');
            $t->integer('medico_id');
            $t->date('fecha');
            $t->time('hora_inicio');
            $t->time('hora_fin');
            $t->string('estado')->nullable()->default('Pendiente');
            $t->string('canal', 20)->default('web');
            // Igual que en MySQL: 1 si la cita está vigente, NULL si está cancelada (ver migración)
            $t->unsignedTinyInteger('turno_activo')->nullable()->storedAs("CASE WHEN estado IS NULL OR estado <> 'Cancelada' THEN 1 END");
            $t->timestamps();
            $t->unique(['medico_id', 'fecha', 'hora_inicio', 'turno_activo'], 'citas_turno_unico');
        });
        Schema::create('historias_clinicas', function (Blueprint $t) {
            $t->id();
            $t->integer('cita_id')->unique();
            $t->text('diagnostico');
            $t->text('tratamiento')->nullable();
            $t->text('receta_medica')->nullable();
            $t->text('observaciones')->nullable();
            $t->timestamps();
        });
        Schema::create('auditorias', function (Blueprint $t) {
            $t->id();
            $t->integer('usuario_id')->nullable();
            $t->string('accion', 100);
            $t->string('tabla_afectada', 50);
            $t->integer('registro_id')->nullable();
            $t->string('direccion_ip', 45)->nullable();
            $t->string('canal', 20)->default('web');
            $t->timestamp('fecha_hora')->nullable()->useCurrent();
        });
        Schema::create('configuraciones', function (Blueprint $t) {
            $t->id();
            $t->string('clave', 100)->unique();
            $t->text('valor')->nullable();
            $t->timestamps();
        });
        // Tabla del Password broker (la crea la migración 0001_01_01_000000)
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        // Mismos IDs que en MySQL
        DB::table('roles')->insert([
            ['id' => Rol::PACIENTE, 'nombre' => 'Paciente'],
            ['id' => Rol::MEDICO, 'nombre' => 'Medico'],
            ['id' => Rol::ADMINISTRADOR, 'nombre' => 'Administrador'],
            ['id' => Rol::RECEPCIONISTA, 'nombre' => 'Recepcionista'],
        ]);
    }

    protected function crearUsuario(int $rolId, string $correo, int $estado = 1, string $contrasena = 'secreto123'): int
    {
        return DB::table('usuarios')->insertGetId([
            'rol_id' => $rolId,
            'correo' => $correo,
            'contrasena' => Hash::make($contrasena),
            'estado' => $estado,
        ]);
    }

    protected function crearEspecialidad(string $nombre, int $estado = 1): int
    {
        return DB::table('especialidades')->insertGetId(['nombre' => $nombre, 'estado' => $estado]);
    }

    // Crea el médico con su usuario (rol Médico); $estado es el del usuario
    protected function crearMedico(int $especialidadId, string $correo, string $apellidos = 'Prueba', int $estado = 1): int
    {
        return DB::table('medicos')->insertGetId([
            'usuario_id' => $this->crearUsuario(Rol::MEDICO, $correo, $estado),
            'especialidad_id' => $especialidadId,
            'colegiatura' => 'CMP-'.strtoupper(substr(md5($correo), 0, 6)),
            'nombres' => 'Doc',
            'apellidos' => $apellidos,
        ]);
    }

    protected function crearPaciente(string $dni, string $nombres = 'Paciente', ?string $creadoEn = null): int
    {
        return DB::table('pacientes')->insertGetId([
            'dni' => $dni,
            'nombres' => $nombres,
            'apellidos' => 'Prueba',
            'created_at' => $creadoEn ?? now(),
        ]);
    }

    protected function crearCita(int $pacienteId, int $medicoId, string $fecha, string $hora = '10:00:00', ?string $estado = 'Pendiente'): int
    {
        return DB::table('citas')->insertGetId([
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'fecha' => $fecha,
            'hora_inicio' => $hora,
            'hora_fin' => date('H:i:s', strtotime($hora) + 30 * 60),
            'estado' => $estado,
            'created_at' => now(),
        ]);
    }
}
