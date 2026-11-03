<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Rol;
use App\Services\CitaService;
use App\Services\DisponibilidadService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    // Los turnos salen de horarios_medicos (ver DisponibilidadService)

    public function procesarMensaje(Request $request)
    {
        $request->validate(['mensaje' => 'required|string|max:500']);
        $mensaje = trim($request->mensaje);

        // El estado de la conversación vive en la sesión del navegador (no en la IP),
        // así dos personas en la misma red no comparten el mismo chat.
        $sesion = $request->session();
        $estadoActual = $sesion->get('chat.estado', 'inicio');

        // ==========================================
        // SALIDA DE EMERGENCIA (ESCAPE HATCH)
        // ==========================================
        $mensajeNormalizado = mb_strtolower($mensaje);
        $palabrasEscape = ['cancelar', 'salir', 'volver', 'inicio', 'detener', 'ya no', 'cancelar proceso'];

        if ($estadoActual !== 'inicio' && in_array($mensajeNormalizado, $palabrasEscape)) {
            $sesion->forget('chat');

            return response()->json([
                'respuesta' => '🚫 Proceso detenido. He cancelado la operación actual. ¿En qué más te puedo ayudar?',
            ]);
        }

        // ==========================================
        // ESTADO 2: ESPERANDO DNI (Para agendar nuevo turno)
        // ==========================================
        if ($estadoActual === 'esperando_dni') {
            $dni = $this->normalizarDni($mensaje);
            if (! $dni) {
                return response()->json(['respuesta' => 'Ese no parece un DNI válido. Escribe solo los 8 números.']);
            }

            $paciente = Paciente::where('dni', $dni)->first();

            if (! $paciente) {
                $sesion->forget('chat');

                return response()->json(['respuesta' => "No encontré ningún paciente registrado con el DNI {$dni}."]);
            }

            $sesion->put('chat.estado', 'esperando_especialidad');
            $sesion->put('chat.paciente_id', $paciente->id);

            return response()->json([
                'respuesta' => "¡Hola {$paciente->nombres}! Te encontré en el sistema. ¿Para qué especialidad deseas agendar tu cita?",
                'opciones' => $this->opcionesEspecialidades(),
            ]);
        }

        // ==========================================
        // ESTADO 3: ESPERANDO ESPECIALIDAD (Enviar opciones de médicos)
        // ==========================================
        if ($estadoActual === 'esperando_especialidad') {
            $especialidad = Especialidad::where('estado', 1)->where('nombre', 'like', "%{$mensaje}%")->first();

            if (! $especialidad) {
                return response()->json(['respuesta' => 'No contamos con esa especialidad. Intenta con otra.']);
            }

            $medicos = Medico::where('especialidad_id', $especialidad->id)->activos()->get();

            if ($medicos->isEmpty()) {
                return response()->json(['respuesta' => "En este momento no hay médicos disponibles para {$especialidad->nombre}."]);
            }

            $opciones = [];
            foreach ($medicos as $med) {
                $opciones[] = ['texto' => "Dr. {$med->nombres} {$med->apellidos}", 'valor' => (string) $med->id];
            }

            $sesion->put('chat.estado', 'esperando_medico');
            $sesion->put('chat.especialidad_id', $especialidad->id);

            return response()->json([
                'respuesta' => "Para {$especialidad->nombre} tenemos a los siguientes médicos disponibles. Por favor, selecciona uno:",
                'opciones' => $opciones,
            ]);
        }

        // ==========================================
        // ESTADO 4: ESPERANDO MÉDICO (Enviar opciones de horarios)
        // ==========================================
        if ($estadoActual === 'esperando_medico') {
            // Solo se acepta un médico de la especialidad elegida en el paso anterior
            $medico = Medico::where('id', $mensaje)
                ->where('especialidad_id', $sesion->get('chat.especialidad_id'))
                ->activos()
                ->first();

            if (! $medico) {
                return response()->json(['respuesta' => 'Opción incorrecta. Por favor selecciona un médico válido.']);
            }

            $sesion->put('chat.medico_id', $medico->id);

            return $this->ofrecerHorarios($sesion, $medico, "Has seleccionado al Dr. {$medico->apellidos}.");
        }

        // ==========================================
        // ESTADO 5: ESPERANDO HORARIO Y GUARDADO FINAL
        // ==========================================
        if ($estadoActual === 'esperando_horario') {
            // Solo se aceptan los horarios libres que se ofrecieron en el paso anterior
            $horario = $sesion->get('chat.horarios', [])[$mensaje] ?? null;

            if (! $horario) {
                return response()->json(['respuesta' => 'Horario no válido. Selecciona una de las opciones mostradas.']);
            }

            $medico = Medico::find($sesion->get('chat.medico_id'));
            [$fechaCita, $horaInicio, $horaFin] = $horario;

            // === ¿ES CREACIÓN O REPROGRAMACIÓN? ===
            $citaReprogramar = null;
            if ($sesion->has('chat.cita_a_reprogramar')) {
                $citaReprogramar = Cita::noCanceladas()
                    ->where('id', $sesion->get('chat.cita_a_reprogramar'))
                    ->where('paciente_id', $sesion->get('chat.paciente_id'))
                    ->first();

                if (! $citaReprogramar) {
                    $sesion->forget('chat');

                    return response()->json(['respuesta' => 'La cita que querías reprogramar ya no está disponible. ¿Te ayudo con algo más?']);
                }
            }

            if (! $medico) {
                $sesion->forget('chat');

                return response()->json(['respuesta' => 'El médico seleccionado ya no está disponible. Por favor, inicia el proceso de nuevo.']);
            }

            try {
                $cita = CitaService::guardar([
                    'paciente_id' => $sesion->get('chat.paciente_id'),
                    'medico_id' => $medico->id,
                    'fecha' => $fechaCita,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                ], $citaReprogramar, 'chatbot');
            } catch (\DomainException $e) {
                // El horario se ocupó mientras el usuario elegía: ofrecemos los libres más cercanos
                return $this->ofrecerAlternativas($sesion, $medico, $fechaCita, $horaInicio, "No se pudo guardar: {$e->getMessage()}");
            }

            $sesion->forget('chat');
            $accion = $citaReprogramar ? 'REPROGRAMADA' : 'agendada';
            $this->auditar(($citaReprogramar ? 'Reprogramó' : 'Agendó')." la cita #{$cita->id} por el chatbot", $cita);

            return response()->json([
                'respuesta' => "¡Listo! Tu cita ha sido {$accion} exitosamente para el ".date('d/m/Y', strtotime($fechaCita)).' a las '.date('g:i A', strtotime($horaInicio))." con el Dr. {$medico->apellidos}. ¿Te ayudo con algo más?",
            ]);
        }

        // ==========================================
        // FLUJO DE CANCELACIÓN DE CITAS
        // ==========================================
        if ($estadoActual === 'cancelar_esperando_dni') {
            return $this->ofrecerCitasVigentes($sesion, $mensaje, 'cancelar');
        }

        if ($estadoActual === 'cancelar_esperando_seleccion') {
            $cita = $this->citaSeleccionada($sesion, $mensaje);

            if (! $cita) {
                return response()->json(['respuesta' => "Selecciona una de las citas mostradas, o escribe 'salir' para detener el proceso."]);
            }

            // No borramos el registro: queda en el historial con estado 'Cancelada' y el turno se libera
            try {
                CitaService::cancelar($cita);
            } catch (\DomainException $e) {
                $sesion->forget('chat');

                return response()->json(['respuesta' => "⚠️ {$e->getMessage()}"]);
            }
            $sesion->forget('chat');
            $this->auditar("Canceló la cita #{$cita->id} por el chatbot", $cita);

            return response()->json(['respuesta' => '✅ Tu cita ha sido cancelada exitosamente.']);
        }

        // ==========================================
        // FLUJO DE REPROGRAMACIÓN DE CITAS
        // ==========================================
        if ($estadoActual === 'reprogramar_esperando_dni') {
            return $this->ofrecerCitasVigentes($sesion, $mensaje, 'reprogramar');
        }

        // Redirección inteligente al flujo de agendamiento
        if ($estadoActual === 'reprogramar_esperando_seleccion') {
            $cita = $this->citaSeleccionada($sesion, $mensaje);

            if (! $cita) {
                return response()->json(['respuesta' => "Selecciona una de las citas mostradas, o escribe 'salir' para detener el proceso."]);
            }

            $sesion->put('chat.cita_a_reprogramar', $cita->id);
            $sesion->put('chat.estado', 'esperando_especialidad');

            return response()->json([
                'respuesta' => 'Excelente, vamos a reagendar tu turno. ¿Para qué especialidad deseas tu nueva cita?',
                'opciones' => $this->opcionesEspecialidades(),
            ]);
        }

        // ==========================================
        // ESTADO 1 (INICIO): MOTOR NLP
        // ==========================================
        try {
            $respuestaPython = Http::timeout(5)->post('http://127.0.0.1:8001/predecir', ['texto' => $mensaje]);
        } catch (ConnectionException $e) {
            return response()->json(['respuesta' => 'El motor NLP de Python está apagado.']);
        }

        if (! $respuestaPython->successful()) {
            return response()->json(['respuesta' => 'El motor NLP tuvo un problema. Intenta de nuevo en unos momentos.']);
        }

        $intencion = $respuestaPython->json('intencion');

        // NUEVA INTENCIÓN: SALUDO
        if ($intencion == 'saludo') {
            return response()->json(['respuesta' => '¡Hola! Soy el asistente virtual de la clínica. Puedes pedirme agendar, cancelar o reprogramar una cita.']);
        }
        // 1. INTENCIÓN: AGENDAR CITA
        elseif ($intencion == 'agendar_cita') {
            // Paciente con sesión iniciada: no hace falta pedirle el DNI
            if ($paciente = $this->pacienteLogueado()) {
                return $this->iniciarAgendamiento($sesion, $paciente);
            }
            $sesion->put('chat.estado', 'esperando_dni');

            return response()->json(['respuesta' => 'Entiendo que deseas agendar una cita. Para buscar tu historial, por favor escribe tu número de DNI.']);
        } elseif ($intencion == 'consultar_especialidades') {
            $nombres = Especialidad::where('estado', 1)->orderBy('nombre')->pluck('nombre');

            if ($nombres->isEmpty()) {
                return response()->json(['respuesta' => 'Por el momento no hay especialidades registradas.']);
            }

            return response()->json(['respuesta' => 'Contamos con especialidades en '.$nombres->join(', ', ' y ').'. Si deseas, puedo agendarte una cita.']);
        } elseif ($intencion == 'consultar_agenda_medico') {
            $medico = (Auth::check() && Auth::user()->rol_id == Rol::MEDICO && Auth::user()->estado == 1)
                ? Medico::where('usuario_id', Auth::id())->first()
                : null;

            if (! $medico) {
                return response()->json(['respuesta' => 'Por seguridad, para consultar la agenda médica debes iniciar sesión en el portal con tus credenciales.']);
            }

            $citas = Cita::with('paciente')
                ->noCanceladas()
                ->where('medico_id', $medico->id)
                ->where('fecha', '>=', date('Y-m-d'))
                ->orderBy('fecha', 'asc')
                ->orderBy('hora_inicio', 'asc')
                ->get();

            if ($citas->isEmpty()) {
                return response()->json(['respuesta' => "Dr. {$medico->apellidos}, no tiene citas programadas en su agenda para los próximos días."]);
            }

            $textoAgenda = "Dr. {$medico->apellidos}, aquí tiene sus próximas citas agendadas:\n\n";
            foreach ($citas as $cita) {
                $textoAgenda .= '📅 Fecha: '.date('d/m/Y', strtotime($cita->fecha))."\n";
                $textoAgenda .= '⏰ Hora: '.date('h:i A', strtotime($cita->hora_inicio))."\n";
                $textoAgenda .= "👤 Paciente: {$cita->paciente->nombres} {$cita->paciente->apellidos}\n────────────────\n";
            }

            return response()->json(['respuesta' => trim($textoAgenda)]);
        } elseif ($intencion == 'cancelar_cita') {
            if ($paciente = $this->pacienteLogueado()) {
                return $this->mostrarCitasVigentes($sesion, $paciente, 'cancelar');
            }
            $sesion->put('chat.estado', 'cancelar_esperando_dni');

            return response()->json(['respuesta' => 'Entiendo que deseas cancelar tu cita. Por favor, escribe tu número de DNI.']);
        } elseif ($intencion == 'reprogramar_cita') {
            if ($paciente = $this->pacienteLogueado()) {
                return $this->mostrarCitasVigentes($sesion, $paciente, 'reprogramar');
            }
            $sesion->put('chat.estado', 'reprogramar_esperando_dni');

            return response()->json(['respuesta' => 'Entiendo que deseas reprogramar un turno. Por favor, escribe tu número de DNI para buscar tus citas vigentes.']);
        }

        // Intención 'desconocida' (o cualquier otra que no manejemos)
        return response()->json(['respuesta' => 'No logré entender tu solicitud. ¿Podrías escribirlo de otra forma?']);
    }

    // Devuelve el DNI con solo dígitos si tiene exactamente 8, o null si no es válido
    // HU18: bitácora con canal 'chatbot'. Si no hay sesión iniciada, el autor es el paciente identificado por DNI.
    private function auditar(string $accion, Cita $cita): void
    {
        Auditoria::registrar($accion, 'citas', $cita->id, Auditoria::CANAL_CHATBOT, Auth::id() ?? $cita->paciente?->usuario_id);
    }

    private function normalizarDni(string $mensaje): ?string
    {
        $dni = preg_replace('/[^0-9]/', '', $mensaje);

        return strlen($dni) === 8 ? $dni : null;
    }

    private function opcionesEspecialidades(): array
    {
        $opciones = [];
        foreach (Especialidad::where('estado', 1)->orderBy('nombre')->get() as $esp) {
            $opciones[] = ['texto' => $esp->nombre, 'valor' => $esp->nombre];
        }

        return $opciones;
    }

    // Ficha del paciente con sesión iniciada (rol Paciente y activo), o null si no hay
    private function pacienteLogueado(): ?Paciente
    {
        $usuario = Auth::user();
        if (! $usuario || $usuario->rol_id != Rol::PACIENTE || $usuario->estado != 1) {
            return null;
        }

        return Paciente::where('usuario_id', $usuario->id)->first();
    }

    // Primer paso del agendamiento una vez identificado el paciente: elegir especialidad
    private function iniciarAgendamiento(Session $sesion, Paciente $paciente)
    {
        $sesion->put('chat.estado', 'esperando_especialidad');
        $sesion->put('chat.paciente_id', $paciente->id);

        return response()->json([
            'respuesta' => "¡Hola {$paciente->nombres}! ¿Para qué especialidad deseas agendar tu cita?",
            'opciones' => $this->opcionesEspecialidades(),
        ]);
    }

    // Busca el primer día (desde mañana) con turnos libres según los horarios del médico y los ofrece.
    // Guarda en sesión la fecha y los horarios ofrecidos para validar la elección.
    private function ofrecerHorarios(Session $sesion, Medico $medico, string $introduccion)
    {
        if (! DisponibilidadService::tieneHorarios($medico)) {
            $sesion->put('chat.estado', 'esperando_especialidad');

            return response()->json([
                'respuesta' => "{$introduccion} El Dr. {$medico->apellidos} aún no tiene horarios de atención configurados. Elige una especialidad para ver otros médicos:",
                'opciones' => $this->opcionesEspecialidades(),
            ]);
        }

        $dias = DisponibilidadService::proximosDias($medico, DisponibilidadService::DIAS_BUSQUEDA, $sesion->get('chat.cita_a_reprogramar'));

        if ($dias) {
            $fecha = array_key_first($dias);

            // Se numeran los turnos ofrecidos: el usuario responde con el número (valor del botón)
            $libres = [];
            $opciones = [];
            foreach ($dias[$fecha] as $indice => $turno) {
                $valor = (string) ($indice + 1);
                $libres[$valor] = [$fecha, $turno['inicio'], $turno['fin']];
                $opciones[] = ['texto' => date('g:i A', strtotime($turno['inicio'])), 'valor' => $valor];
            }

            $sesion->put('chat.estado', 'esperando_horario');
            $sesion->put('chat.horarios', $libres);

            return response()->json([
                'respuesta' => "{$introduccion} Para el ".date('d/m/Y', strtotime($fecha)).' tiene disponibles estos horarios:',
                'opciones' => $opciones,
            ]);
        }

        // Sin turnos libres: volvemos a la elección de especialidad/médico
        $sesion->put('chat.estado', 'esperando_especialidad');

        return response()->json([
            'respuesta' => "{$introduccion} Lamentablemente no tiene horarios libres en los próximos ".DisponibilidadService::DIAS_BUSQUEDA.' días. Elige una especialidad para ver otros médicos:',
            'opciones' => $this->opcionesEspecialidades(),
        ]);
    }

    // Conflicto de horario: ofrece los turnos libres más cercanos al pedido (pueden ser de otro día).
    // Si el médico ya no tiene ninguno, ofrece otros médicos de la misma especialidad.
    private function ofrecerAlternativas(Session $sesion, Medico $medico, string $fecha, string $inicio, string $introduccion)
    {
        $alternativas = DisponibilidadService::alternativas($medico, $fecha, $inicio, 3, $sesion->get('chat.cita_a_reprogramar'));

        if ($alternativas) {
            $libres = [];
            $opciones = [];
            foreach ($alternativas as $indice => $turno) {
                $valor = (string) ($indice + 1);
                $libres[$valor] = [$turno['fecha'], $turno['inicio'], $turno['fin']];
                $opciones[] = ['texto' => date('d/m/Y', strtotime($turno['fecha'])).' · '.date('g:i A', strtotime($turno['inicio'])), 'valor' => $valor];
            }

            $sesion->put('chat.estado', 'esperando_horario');
            $sesion->put('chat.horarios', $libres);

            return response()->json([
                'respuesta' => "{$introduccion} Estos son los horarios libres más cercanos con el Dr. {$medico->apellidos}:",
                'opciones' => $opciones,
            ]);
        }

        $otros = Medico::activos()
            ->where('especialidad_id', $medico->especialidad_id)
            ->where('id', '!=', $medico->id)
            ->orderBy('apellidos')
            ->get();

        if ($otros->isEmpty()) {
            return $this->ofrecerHorarios($sesion, $medico, $introduccion);
        }

        $sesion->put('chat.estado', 'esperando_medico');
        $sesion->put('chat.especialidad_id', $medico->especialidad_id);

        return response()->json([
            'respuesta' => "{$introduccion} El Dr. {$medico->apellidos} ya no tiene horarios libres. Puedes elegir otro médico de la especialidad:",
            'opciones' => $otros->map(fn ($m) => ['texto' => "Dr. {$m->nombres} {$m->apellidos}", 'valor' => (string) $m->id])->all(),
        ]);
    }

    // Paso común de cancelar/reprogramar cuando se pidió el DNI
    private function ofrecerCitasVigentes(Session $sesion, string $mensaje, string $accion)
    {
        $dni = $this->normalizarDni($mensaje);
        if (! $dni) {
            return response()->json(['respuesta' => 'Por favor, ingresa un DNI válido de 8 dígitos.']);
        }

        $paciente = Paciente::where('dni', $dni)->first();
        if (! $paciente) {
            $sesion->forget('chat');

            return response()->json(['respuesta' => 'No encontramos un paciente registrado con ese DNI.']);
        }

        return $this->mostrarCitasVigentes($sesion, $paciente, $accion);
    }

    // Muestra las citas que el paciente aún puede cancelar o reprogramar
    private function mostrarCitasVigentes(Session $sesion, Paciente $paciente, string $accion)
    {
        $citas = Cita::modificables()
            ->where('paciente_id', $paciente->id)
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->get();

        if ($citas->isEmpty()) {
            $sesion->forget('chat');

            return response()->json(['respuesta' => "No tienes citas pendientes para {$accion}."]);
        }

        // Para cancelar se respeta la anticipación mínima: las citas muy próximas no se ofrecen
        if ($accion === 'cancelar') {
            $citas = $citas->filter(fn (Cita $cita) => $cita->puedeCancelarse())->values();

            if ($citas->isEmpty()) {
                $sesion->forget('chat');
                $horas = config('clinica.cancelacion_horas_minimas');

                return response()->json(['respuesta' => "Tus citas pendientes son dentro de menos de {$horas} horas y ya no se pueden cancelar por este medio. Comunícate con recepción al ".config('clinica.telefonos.fijo').'.']);
            }
        }

        $sesion->put('chat.estado', "{$accion}_esperando_seleccion");
        $sesion->put('chat.paciente_id', $paciente->id);
        $sesion->put('chat.citas_ofrecidas', $citas->pluck('id')->map(fn ($id) => (string) $id)->all());

        $etiqueta = $accion === 'cancelar' ? '❌ Cancelar' : '🔄 Reprogramar';
        $opciones = [];
        foreach ($citas as $cita) {
            $fechaFormato = date('d/m/Y', strtotime($cita->fecha));
            $horaFormato = date('h:i A', strtotime($cita->hora_inicio));
            $opciones[] = ['texto' => "{$etiqueta} cita del {$fechaFormato} a las {$horaFormato}", 'valor' => (string) $cita->id];
        }

        $respuesta = $accion === 'cancelar'
            ? 'Encontré estos turnos a tu nombre. Haz clic en la cita que deseas cancelar:'
            : 'Encontré estos turnos a tu nombre. Selecciona la cita que deseas reprogramar:';

        return response()->json(['respuesta' => $respuesta, 'opciones' => $opciones]);
    }

    // Solo acepta una cita que se le mostró a este mismo paciente en el paso anterior
    private function citaSeleccionada(Session $sesion, string $mensaje): ?Cita
    {
        if (! in_array($mensaje, $sesion->get('chat.citas_ofrecidas', []), true)) {
            return null;
        }

        return Cita::modificables()
            ->where('id', $mensaje)
            ->where('paciente_id', $sesion->get('chat.paciente_id'))
            ->first();
    }
}
