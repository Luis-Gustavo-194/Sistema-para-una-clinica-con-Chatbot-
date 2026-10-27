<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Concerns\EsquemaClinica;
use App\Models\Cita;
use App\Models\Usuario;

// Flujos completos del chatbot (agendar, cancelar, reprogramar), accesos por rol y login.
// El motor NLP de Python se simula con Http::fake.
class ChatbotFlujosTest extends TestCase
{
    use EsquemaClinica;

    private string $intencion = 'saludo';
    private array $cookies = []; // cookie de sesión por "navegador"
    private string $manana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manana = date('Y-m-d', strtotime('+1 day'));
        $this->crearEsquemaClinica();

        DB::table('usuarios')->insert([
            ['id' => 1, 'rol_id' => 3, 'correo' => 'admin@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
            ['id' => 2, 'rol_id' => 2, 'correo' => 'medico@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
            ['id' => 3, 'rol_id' => 2, 'correo' => 'sinperfil@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
            ['id' => 4, 'rol_id' => 1, 'correo' => 'paciente@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
            ['id' => 5, 'rol_id' => 1, 'correo' => 'inactivo@x.com', 'contrasena' => Hash::make('x'), 'estado' => 0],
            ['id' => 6, 'rol_id' => 2, 'correo' => 'pediatra@x.com', 'contrasena' => Hash::make('x'), 'estado' => 1],
        ]);
        DB::table('especialidades')->insert([['id' => 1, 'nombre' => 'Cardiología'], ['id' => 2, 'nombre' => 'Pediatría']]);
        DB::table('medicos')->insert([
            ['id' => 1, 'usuario_id' => 2, 'especialidad_id' => 1, 'colegiatura' => 'CMP-1', 'nombres' => 'Ana', 'apellidos' => 'Ríos'],
            ['id' => 2, 'usuario_id' => 6, 'especialidad_id' => 2, 'colegiatura' => 'CMP-2', 'nombres' => 'Luis', 'apellidos' => 'Paz'],
        ]);
        DB::table('pacientes')->insert([
            ['id' => 1, 'usuario_id' => 4, 'dni' => '11111111', 'nombres' => 'Juan', 'apellidos' => 'Pérez'],
            ['id' => 2, 'usuario_id' => null, 'dni' => '22222222', 'nombres' => 'María', 'apellidos' => 'López'],
        ]);

        // Ambos médicos atienden todos los días en dos turnos: 10:00-10:30 y 11:30-12:00
        foreach ([1, 2] as $medicoId) {
            for ($dia = 1; $dia <= 7; $dia++) {
                DB::table('horarios_medicos')->insert([
                    ['medico_id' => $medicoId, 'dia_semana' => $dia, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'duracion_consulta_minutos' => 30],
                    ['medico_id' => $medicoId, 'dia_semana' => $dia, 'hora_inicio' => '11:30:00', 'hora_fin' => '12:00:00', 'duracion_consulta_minutos' => 30],
                ]);
            }
        }

        Http::fake(['127.0.0.1:8001/*' => fn () => Http::response(['intencion' => $this->intencion])]);
    }

    // Envía un mensaje simulando un navegador con su propia cookie de sesión
    private function enviar(string $mensaje, string $navegador = 'A'): array
    {
        // Como en producción: cada petición empieza sin datos en memoria y solo
        // recupera la sesión a partir de la cookie que envía el navegador
        app('session')->driver()->flush();

        $this->defaultCookies = []; // no arrastrar la cookie de otro navegador
        $req = $this->withCredentials();
        if (isset($this->cookies[$navegador])) {
            $req = $req->withCookie(config('session.cookie'), $this->cookies[$navegador]);
        }
        $res = $req->postJson('/chatbot/enviar', ['mensaje' => $mensaje]);
        $res->assertOk();
        $cookie = $res->getCookie(config('session.cookie'));
        if ($cookie) {
            $this->cookies[$navegador] = $cookie->getValue();
        }
        return $res->json();
    }

    private function agendar(string $dni, string $navegador = 'A'): array
    {
        $this->intencion = 'agendar_cita';
        $this->enviar('quiero una cita', $navegador);
        $this->enviar($dni, $navegador);
        $this->enviar('Cardiología', $navegador);
        return $this->enviar('1', $navegador); // médico id 1 -> devuelve horarios
    }

    public function test_agendar_y_horarios_ocupados_no_se_ofrecen(): void
    {
        $r = $this->agendar('11111111');
        $this->assertSame(['10:00 AM', '11:30 AM'], array_column($r['opciones'], 'texto'));
        $r = $this->enviar('1');
        $this->assertStringContainsString('agendada exitosamente', $r['respuesta']);
        $this->assertDatabaseHas('citas', ['paciente_id' => 1, 'medico_id' => 1, 'fecha' => $this->manana, 'hora_inicio' => '10:00:00', 'estado' => 'Pendiente']);

        // Otro navegador/paciente: el turno de las 10:00 ya no se ofrece y no se puede forzar
        $r = $this->agendar('22222222', 'B');
        $this->assertSame(['11:30 AM'], array_column($r['opciones'], 'texto'));
        $r = $this->enviar('2', 'B'); // no se ofreció: no se puede forzar
        $this->assertStringContainsString('Horario no válido', $r['respuesta']);
        $this->assertSame(1, Cita::count());
    }

    public function test_estado_no_se_comparte_entre_navegadores(): void
    {
        $this->intencion = 'agendar_cita';
        $this->enviar('quiero una cita', 'A');
        // B (misma IP en el test) no está en medio del flujo: su DNI va al NLP
        $this->intencion = 'saludo';
        $r = $this->enviar('11111111', 'B');
        $this->assertStringContainsString('asistente virtual', $r['respuesta']);
    }

    public function test_medico_de_otra_especialidad_rechazado(): void
    {
        $this->intencion = 'agendar_cita';
        $this->enviar('cita');
        $this->enviar('11111111');
        $this->enviar('Cardiología');
        $r = $this->enviar('2'); // médico de Pediatría
        $this->assertStringContainsString('Opción incorrecta', $r['respuesta']);
    }

    public function test_cancelar_solo_citas_propias_y_marca_estado(): void
    {
        $ajena = Cita::create(['paciente_id' => 1, 'medico_id' => 1, 'fecha' => $this->manana, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'Pendiente']);
        $propia = Cita::create(['paciente_id' => 2, 'medico_id' => 2, 'fecha' => $this->manana, 'hora_inicio' => '11:30:00', 'hora_fin' => '12:00:00', 'estado' => 'Pendiente']);

        $this->intencion = 'cancelar_cita';
        $this->enviar('cancela mi cita');
        $r = $this->enviar('22222222');
        $this->assertSame([(string) $propia->id], array_column($r['opciones'], 'valor'));

        $r = $this->enviar((string) $ajena->id);
        $this->assertStringContainsString('Selecciona una de las citas', $r['respuesta']);
        $this->assertSame('Pendiente', $ajena->fresh()->estado);

        $r = $this->enviar((string) $propia->id);
        $this->assertStringContainsString('cancelada exitosamente', $r['respuesta']);
        $this->assertSame('Cancelada', $propia->fresh()->estado); // no se borra

        // Una cita cancelada ya no se ofrece
        $this->intencion = 'cancelar_cita';
        $this->enviar('cancela mi cita');
        $r = $this->enviar('22222222');
        $this->assertStringContainsString('No tienes citas pendientes', $r['respuesta']);
    }

    public function test_reprogramar_respeta_choques_y_actualiza(): void
    {
        // El médico 1 ya tiene las 10:00 de mañana ocupadas por otro paciente
        Cita::create(['paciente_id' => 1, 'medico_id' => 1, 'fecha' => $this->manana, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'Pendiente']);
        $mia = Cita::create(['paciente_id' => 2, 'medico_id' => 2, 'fecha' => $this->manana, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => null]);

        $this->intencion = 'reprogramar_cita';
        $this->enviar('cambiar mi cita');
        $r = $this->enviar('22222222');
        $this->assertSame([(string) $mia->id], array_column($r['opciones'], 'valor')); // estado NULL cuenta como vigente
        $this->enviar((string) $mia->id);
        $this->enviar('Cardiología');
        $r = $this->enviar('1');
        $this->assertSame(['11:30 AM'], array_column($r['opciones'], 'texto')); // 10:00 ocupada
        $r = $this->enviar('1');
        $this->assertStringContainsString('REPROGRAMADA', $r['respuesta']);
        $mia->refresh();
        $this->assertSame(1, $mia->medico_id);
        $this->assertSame('11:30:00', $mia->hora_inicio);
        $this->assertSame(2, Cita::count());
    }

    public function test_choque_en_carrera_vuelve_a_ofrecer_horarios(): void
    {
        $this->agendar('11111111'); // ofrece 1 y 2
        // Mientras elige, otro paciente toma las 10:00
        Cita::create(['paciente_id' => 2, 'medico_id' => 1, 'fecha' => $this->manana, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'Pendiente']);
        $r = $this->enviar('1');
        $this->assertStringContainsString('No se pudo guardar', $r['respuesta']);
        $this->assertSame(['11:30 AM'], array_column($r['opciones'], 'texto'));
    }

    public function test_especialidades_desde_bd_desconocida_y_python_apagado(): void
    {
        $this->intencion = 'consultar_especialidades';
        $r = $this->enviar('qué especialidades hay');
        $this->assertStringContainsString('Cardiología y Pediatría', $r['respuesta']);

        $this->intencion = 'desconocida';
        $r = $this->enviar('precio del dólar');
        $this->assertStringContainsString('No logré entender', $r['respuesta']);

        Http::fake(['127.0.0.1:8001/*' => fn () => throw new ConnectionException('down')]);
        $r = $this->enviar('hola');
        $this->assertStringContainsString('apagado', $r['respuesta']);
    }

    public function test_middleware_de_roles(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->actingAs(Usuario::find(2))->get('/admin/dashboard')->assertRedirect('/login');
        $this->actingAs(Usuario::find(1))->get('/admin/dashboard')->assertOk()->assertSee('Estado');
        $this->actingAs(Usuario::find(1))->get('/dashboard-medico')->assertRedirect('/login');
        $this->actingAs(Usuario::find(3))->get('/dashboard-medico')->assertForbidden();
        $this->actingAs(Usuario::find(2))->get('/dashboard-medico')->assertOk();
        // Un paciente (rol 1) ya no entra al panel de administración
        $this->actingAs(Usuario::find(4))->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_login_redirige_por_rol_y_bloquea_inactivos(): void
    {
        $this->post('/login', ['correo' => 'inactivo@x.com', 'contrasena' => 'x'])->assertSessionHasErrors('correo');
        $this->assertGuest();
        $this->post('/login', ['correo' => 'admin@x.com', 'contrasena' => 'x'])->assertRedirect('/admin/dashboard');
        $this->post('/logout');
        app('auth')->forgetGuards();
        $this->post('/login', ['correo' => 'medico@x.com', 'contrasena' => 'x'])->assertRedirect('/dashboard-medico');
        app('auth')->forgetGuards();
        $this->post('/login', ['correo' => 'paciente@x.com', 'contrasena' => 'x'])->assertRedirect('/paciente');
    }

    public function test_api_agendar_valida_datos(): void
    {
        $this->postJson('/api/citas/agendar', ['paciente_id' => 99])->assertStatus(422);
        $ok = ['paciente_id' => 1, 'medico_id' => 1, 'fecha' => $this->manana, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00'];
        $this->postJson('/api/citas/agendar', $ok)->assertStatus(201);
        $this->postJson('/api/citas/agendar', $ok)->assertStatus(400)->assertJsonStructure(['error']);
    }

    public function test_especialidad_inactiva_no_se_ofrece(): void
    {
        DB::table('especialidades')->where('id', 2)->update(['estado' => 0]);

        $this->intencion = 'agendar_cita';
        $this->enviar('cita');
        $r = $this->enviar('11111111');
        $this->assertSame(['Cardiología'], array_column($r['opciones'], 'valor'));
        $r = $this->enviar('Pediatría');
        $this->assertStringContainsString('No contamos con esa especialidad', $r['respuesta']);
        $this->enviar('salir');

        $this->intencion = 'consultar_especialidades';
        $r = $this->enviar('qué especialidades hay');
        $this->assertStringContainsString('Cardiología', $r['respuesta']);
        $this->assertStringNotContainsString('Pediatría', $r['respuesta']);
    }

    public function test_paciente_con_sesion_no_necesita_dni(): void
    {
        $this->actingAs(Usuario::find(4)); // paciente@x.com = Juan (paciente 1)

        $this->intencion = 'agendar_cita';
        $r = $this->enviar('quiero una cita');
        $this->assertStringContainsString('¡Hola Juan!', $r['respuesta']);
        $this->assertSame(['Cardiología', 'Pediatría'], array_column($r['opciones'], 'valor'));
        $this->enviar('Cardiología');
        $this->enviar('1');
        $r = $this->enviar('1');
        $this->assertStringContainsString('agendada exitosamente', $r['respuesta']);
        $cita = Cita::first();
        $this->assertSame(1, (int) $cita->paciente_id);

        $this->intencion = 'cancelar_cita';
        $r = $this->enviar('cancelar mi cita');
        $this->assertSame([(string) $cita->id], array_column($r['opciones'], 'valor'));
    }

    public function test_medico_sin_horarios_no_ofrece_turnos(): void
    {
        $sinHorario = $this->crearMedico(1, 'nuevo.cardio@x.com', 'Nuevo');

        $this->intencion = 'agendar_cita';
        $this->enviar('cita');
        $this->enviar('11111111');
        $this->enviar('Cardiología');
        $r = $this->enviar((string) $sinHorario);
        $this->assertStringContainsString('aún no tiene horarios', $r['respuesta']);
    }

    public function test_medico_inactivo_no_se_ofrece(): void
    {
        $inactivo = $this->crearMedico(1, 'otro.cardiologo@x.com', 'Inactivo', 0);

        $this->intencion = 'agendar_cita';
        $this->enviar('cita');
        $this->enviar('11111111');
        $r = $this->enviar('Cardiología');
        $this->assertSame(['1'], array_column($r['opciones'], 'valor'));
        $r = $this->enviar((string) $inactivo);
        $this->assertStringContainsString('Opción incorrecta', $r['respuesta']);
    }
}