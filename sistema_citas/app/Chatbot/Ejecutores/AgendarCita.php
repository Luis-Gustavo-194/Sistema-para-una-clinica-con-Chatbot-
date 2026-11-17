<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Conversacion;
use App\Chatbot\Entidades;
use App\Chatbot\ExtractorEntidades;
use App\Chatbot\NormalizadorTexto;
use App\Chatbot\Respuesta;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Services\CitaService;
use App\Services\DisponibilidadService;
use Illuminate\Support\Carbon;

// Agenda (o reprograma, si hay cita_a_reprogramar) una cita llenando los datos que falten:
// paciente → especialidad → médico → horario → resumen → confirmación.
// Los datos que el paciente escriba en cualquier mensaje ("cardiología mañana a las 10")
// se aprovechan para saltarse preguntas o para corregir lo ya elegido.
class AgendarCita implements Ejecutor
{
    use IdentificaPaciente;

    const FLUJO = 'agendar_cita';

    // Respuestas que confirman el resumen (texto normalizado)
    const CONFIRMAR = ['confirmar', 'confirmo', 'si', 'si confirmo', 'ok', 'dale', 'de acuerdo', 'correcto', 'listo'];

    public function __construct(private ExtractorEntidades $extractor) {}

    public function iniciar(Conversacion $conversacion, Entidades $entidades): Respuesta
    {
        $conversacion->iniciar(self::FLUJO);
        $this->aplicar($conversacion, $entidades);

        // Paciente con sesión iniciada: no hace falta pedirle el DNI
        if ($paciente = $this->pacienteLogueado()) {
            $conversacion->guardar(['paciente_id' => $paciente->id]);

            return $this->avanzar($conversacion, "¡Hola {$paciente->nombres}!");
        }

        if ($entidades->dni) {
            return $this->identificar($conversacion, $entidades->dni);
        }

        $conversacion->irA('dni');

        return new Respuesta('Entiendo que deseas agendar una cita. Para buscar tu historial, por favor escribe tu número de DNI.');
    }

    public function continuar(Conversacion $conversacion, string $mensaje, Entidades $entidades): Respuesta
    {
        return match ($conversacion->paso()) {
            'dni' => $this->recibirDni($conversacion, $entidades),
            'especialidad' => $this->elegirEspecialidad($conversacion, $entidades),
            'medico' => $this->elegirMedico($conversacion, $mensaje, $entidades),
            'horario' => $this->elegirHorario($conversacion, $mensaje, $entidades),
            'confirmando' => $this->confirmar($conversacion, $mensaje, $entidades),
            default => $this->avanzar($conversacion),
        };
    }

    // "Atrás" vuelve a la pregunta anterior; "cambiar X" borra X y lo que depende de X
    public function corregir(Conversacion $conversacion, ?string $campo): Respuesta
    {
        $campo ??= match ($conversacion->paso()) {
            'confirmando' => 'horario',
            'horario' => 'medico',
            'medico' => 'especialidad',
            'especialidad' => 'dni',
            default => null,
        };

        switch ($campo) {
            case 'dni':
                if ($this->pacienteLogueado()) {
                    return $this->avanzar($conversacion, 'Estás identificado con tu sesión, así que no hace falta cambiar el DNI.');
                }
                if ($conversacion->dato('cita_a_reprogramar')) {
                    return $this->avanzar($conversacion, 'Para reprogramar la cita de otra persona escribe "cancelar" y empieza de nuevo.');
                }
                $conversacion->olvidar('paciente_id');
                $conversacion->irA('dni');

                return new Respuesta('De acuerdo. Escribe el número de DNI del paciente (8 dígitos).');

            case 'especialidad':
                $conversacion->olvidar('especialidad_id', 'medico_id', 'turno', 'fecha', 'hora');

                return $this->avanzar($conversacion, 'De acuerdo, cambiemos la especialidad.');

            case 'medico':
                $conversacion->olvidar('medico_id', 'turno', 'fecha', 'hora');

                return $this->avanzar($conversacion, 'De acuerdo, elige otro médico.');

            case 'fecha':
            case 'horario':
                $conversacion->olvidar('turno', 'fecha', 'hora');

                return $this->avanzar($conversacion, 'De acuerdo, elige otro horario. También puedes escribirme el día y la hora, por ejemplo: "el viernes a las 10".');

            default:
                return new Respuesta('Estás en el primer paso. Escribe tu DNI de 8 dígitos, o "cancelar" para salir.');
        }
    }

    // Pide el primer dato que falte y, cuando ya están todos, muestra el resumen para confirmar
    public function avanzar(Conversacion $conversacion, string $introduccion = ''): Respuesta
    {
        $intro = $introduccion !== '' ? "{$introduccion} " : '';

        if (! $conversacion->dato('paciente_id')) {
            $conversacion->irA('dni');

            return new Respuesta("{$intro}Para continuar, escribe tu número de DNI.");
        }

        $especialidad = Especialidad::where('estado', 1)->find($conversacion->dato('especialidad_id'));
        $medico = $especialidad ? Medico::activos()->where('especialidad_id', $especialidad->id)->find($conversacion->dato('medico_id')) : null;
        if (! $especialidad) {
            $conversacion->olvidar('especialidad_id', 'medico_id', 'turno');
        } elseif (! $medico) {
            $conversacion->olvidar('medico_id', 'turno');
        }

        if (! $especialidad) {
            $pregunta = $conversacion->dato('cita_a_reprogramar') ? '¿Para qué especialidad deseas tu nueva cita?' : '¿Para qué especialidad deseas agendar tu cita?';

            return $this->preguntarEspecialidad($conversacion, $intro.$pregunta);
        }

        if (! $medico) {
            return $this->preguntarMedico($conversacion, $especialidad, $intro);
        }

        if (! DisponibilidadService::tieneHorarios($medico)) {
            $conversacion->olvidar('especialidad_id', 'medico_id');

            return $this->preguntarEspecialidad($conversacion, "{$intro}El Dr. {$medico->apellidos} aún no tiene horarios de atención configurados. Elige una especialidad para ver otros médicos:");
        }

        if ($conversacion->dato('turno')) {
            return $this->resumen($conversacion);
        }

        return $this->buscarTurno($conversacion, $medico, $intro);
    }

    private function recibirDni(Conversacion $conversacion, Entidades $entidades): Respuesta
    {
        $this->aplicar($conversacion, $entidades);

        return $this->identificar($conversacion, $entidades->dni);
    }

    private function identificar(Conversacion $conversacion, ?string $dni): Respuesta
    {
        $resultado = $this->buscarPaciente($dni);

        if ($resultado instanceof Respuesta) {
            $conversacion->irA('dni');

            return $resultado;
        }

        $conversacion->guardar(['paciente_id' => $resultado->id]);

        return $this->avanzar($conversacion, "¡Hola {$resultado->nombres}! Te encontré en el sistema.");
    }

    private function elegirEspecialidad(Conversacion $conversacion, Entidades $entidades): Respuesta
    {
        if (! $entidades->especialidad && ! $entidades->medico) {
            $this->aplicar($conversacion, $entidades);

            return new Respuesta('No contamos con esa especialidad. Intenta con otra.', $this->opcionesEspecialidades());
        }

        $this->aplicar($conversacion, $entidades);

        return $this->avanzar($conversacion);
    }

    private function elegirMedico(Conversacion $conversacion, string $mensaje, Entidades $entidades): Respuesta
    {
        $especialidadId = $conversacion->dato('especialidad_id');

        // Botón (id) o nombre escrito; con un número solo se aceptan médicos de la especialidad elegida
        $medico = ctype_digit($mensaje)
            ? Medico::activos()->where('especialidad_id', $especialidadId)->find((int) $mensaje)
            : ($entidades->medico ?? $this->extractor->medico(NormalizadorTexto::normalizar($mensaje), true, $especialidadId));

        if ($medico) {
            $this->aplicar($conversacion, new Entidades(medico: $medico, fecha: $entidades->fecha, hora: $entidades->hora));

            return $this->avanzar($conversacion, "Has seleccionado al Dr. {$medico->apellidos}.");
        }

        if (! ctype_digit($mensaje) && $this->aplicar($conversacion, $entidades)) {
            return $this->avanzar($conversacion);
        }

        return new Respuesta('Opción incorrecta. Por favor selecciona un médico válido.', $conversacion->dato('opciones', []));
    }

    private function elegirHorario(Conversacion $conversacion, string $mensaje, Entidades $entidades): Respuesta
    {
        // Solo se aceptan los horarios que se ofrecieron (valor del botón)
        if ($turno = $conversacion->dato('horarios', [])[$mensaje] ?? null) {
            return $this->tomarTurno($conversacion, ...$turno);
        }

        if ($this->aplicar($conversacion, $entidades)) {
            return $this->avanzar($conversacion);
        }

        return new Respuesta('Horario no válido. Selecciona una de las opciones mostradas o escríbeme el día y la hora.', $conversacion->dato('opciones', []));
    }

    private function confirmar(Conversacion $conversacion, string $mensaje, Entidades $entidades): Respuesta
    {
        if (in_array(NormalizadorTexto::normalizar($mensaje), self::CONFIRMAR, true)) {
            return $this->guardarCita($conversacion);
        }

        // Corrección libre: "mejor a las 11:30", "mejor el jueves", "con la Dra. Paz"
        if ($this->aplicar($conversacion, $entidades)) {
            return $this->avanzar($conversacion, 'Perfecto, actualicé los datos.');
        }

        return new Respuesta('Pulsa "Confirmar" para guardar la cita, o elige qué quieres cambiar.', $this->opcionesResumen());
    }

    // Copia en la conversación los datos que trae el mensaje. Si ya había un turno elegido,
    // se conserva lo que no se cambió (p. ej. "mejor el jueves" mantiene la misma hora).
    private function aplicar(Conversacion $conversacion, Entidades $entidades): bool
    {
        $nuevos = [];

        if ($entidades->medico) {
            $nuevos['medico_id'] = $entidades->medico->id;
            $nuevos['especialidad_id'] = $entidades->medico->especialidad_id;
        } elseif ($entidades->especialidad && (int) $conversacion->dato('especialidad_id') !== $entidades->especialidad->id) {
            $nuevos['especialidad_id'] = $entidades->especialidad->id;
            $conversacion->olvidar('medico_id');
        }
        if ($entidades->fecha) {
            $nuevos['fecha'] = $entidades->fecha;
        }
        if ($entidades->hora) {
            $nuevos['hora'] = $entidades->hora;
        }

        if ($nuevos === []) {
            return false;
        }

        if ($turno = $conversacion->dato('turno')) {
            $nuevos += ['fecha' => $turno[0], 'hora' => substr($turno[1], 0, 5)];
        }

        $conversacion->olvidar('turno', 'horarios', 'opciones');
        $conversacion->guardar($nuevos);

        return true;
    }

    // Con fecha y/o hora pedidas busca ese turno; si no, ofrece el primer día con turnos libres
    private function buscarTurno(Conversacion $conversacion, Medico $medico, string $intro): Respuesta
    {
        $excluir = $conversacion->dato('cita_a_reprogramar');
        $fecha = $conversacion->dato('fecha');
        $hora = $conversacion->dato('hora');
        $conversacion->olvidar('fecha', 'hora');

        if ($fecha) {
            $dia = Carbon::parse($fecha)->startOfDay();
            $limite = today()->addDays(DisponibilidadService::DIAS_BUSQUEDA);
            if ($dia->lte(today())) {
                $intro .= 'Las citas por este medio se reservan desde mañana. ';
                $fecha = null;
            } elseif ($dia->gt($limite)) {
                $intro .= 'Solo puedo reservar hasta el '.$limite->format('d/m/Y').'. ';
                $fecha = null;
            }
        }

        if ($fecha && $hora) {
            if ($turno = DisponibilidadService::esTurnoValido($medico, $fecha, "{$hora}:00", $excluir)) {
                $conversacion->guardar(['turno' => [$fecha, $turno['inicio'], $turno['fin']]]);

                return $this->resumen($conversacion);
            }

            return $this->ofrecerAlternativas($conversacion, $medico, $fecha, "{$hora}:00", $intro.'El '.date('d/m/Y', strtotime($fecha)).' a las '.date('g:i A', strtotime($hora))." no está disponible con el Dr. {$medico->apellidos}.");
        }

        if ($fecha) {
            $turnos = DisponibilidadService::turnosDelDia($medico, Carbon::parse($fecha), $excluir);

            return $turnos
                ? $this->ofrecerTurnosDelDia($conversacion, $medico, $fecha, $turnos, $intro)
                : $this->ofrecerAlternativas($conversacion, $medico, $fecha, '08:00:00', "{$intro}El Dr. {$medico->apellidos} no tiene horarios libres el ".date('d/m/Y', strtotime($fecha)).'.');
        }

        $dias = DisponibilidadService::proximosDias($medico, DisponibilidadService::DIAS_BUSQUEDA, $excluir);

        if ($hora) {
            foreach ($dias as $dia => $turnos) {
                foreach ($turnos as $turno) {
                    if ($turno['inicio'] === "{$hora}:00") {
                        $conversacion->guardar(['turno' => [$dia, $turno['inicio'], $turno['fin']]]);

                        return $this->resumen($conversacion);
                    }
                }
            }
            $intro .= 'No encontré turnos a las '.date('g:i A', strtotime($hora)).' en los próximos días. ';
        }

        if ($dias) {
            $fecha = array_key_first($dias);

            return $this->ofrecerTurnosDelDia($conversacion, $medico, $fecha, $dias[$fecha], $intro);
        }

        // Sin turnos libres: volvemos a la elección de especialidad/médico
        $conversacion->olvidar('especialidad_id', 'medico_id');

        return $this->preguntarEspecialidad($conversacion, "{$intro}Lamentablemente el Dr. {$medico->apellidos} no tiene horarios libres en los próximos ".DisponibilidadService::DIAS_BUSQUEDA.' días. Elige una especialidad para ver otros médicos:');
    }

    // Al elegir un horario se comprueba que siga libre antes de mostrar el resumen
    private function tomarTurno(Conversacion $conversacion, string $fecha, string $inicio, string $fin): Respuesta
    {
        $medico = Medico::activos()->find($conversacion->dato('medico_id'));
        if (! $medico) {
            return $this->corregir($conversacion, 'medico');
        }

        if (! DisponibilidadService::esTurnoValido($medico, $fecha, $inicio, $conversacion->dato('cita_a_reprogramar'))) {
            return $this->ofrecerAlternativas($conversacion, $medico, $fecha, $inicio, 'Ese horario ya no está disponible: alguien lo acaba de tomar.');
        }

        $conversacion->olvidar('horarios', 'opciones');
        $conversacion->guardar(['turno' => [$fecha, $inicio, $fin]]);

        return $this->resumen($conversacion);
    }

    private function resumen(Conversacion $conversacion): Respuesta
    {
        [$fecha, $inicio] = $conversacion->dato('turno');
        $medico = Medico::with('especialidad')->find($conversacion->dato('medico_id'));
        $conversacion->irA('confirmando');

        $lineas = [
            $conversacion->dato('cita_a_reprogramar') ? 'Revisa los nuevos datos de tu cita:' : 'Revisa los datos de tu cita:',
            "🩺 Especialidad: {$medico->especialidad->nombre}",
            "👨‍⚕️ Médico: Dr(a). {$medico->nombres} {$medico->apellidos}",
            '📅 Fecha: '.ucfirst(Carbon::parse($fecha)->translatedFormat('l d/m/Y')),
            '⏰ Hora: '.date('g:i A', strtotime($inicio)),
        ];
        if ($consultorio = DisponibilidadService::consultorio($medico, $fecha)) {
            $lineas[] = "🏥 Consultorio: {$consultorio}";
        }
        $lineas[] = '¿Confirmas la cita?';

        return new Respuesta(implode("\n", $lineas), $this->opcionesResumen());
    }

    private function guardarCita(Conversacion $conversacion): Respuesta
    {
        [$fecha, $inicio, $fin] = $conversacion->dato('turno');
        $medico = Medico::activos()->find($conversacion->dato('medico_id'));
        if (! $medico) {
            return $this->corregir($conversacion, 'medico');
        }

        // === ¿ES CREACIÓN O REPROGRAMACIÓN? ===
        $citaReprogramar = null;
        if ($id = $conversacion->dato('cita_a_reprogramar')) {
            $citaReprogramar = Cita::noCanceladas()->whereKey($id)->where('paciente_id', $conversacion->dato('paciente_id'))->first();

            if (! $citaReprogramar) {
                $conversacion->terminar();

                return new Respuesta('La cita que querías reprogramar ya no está disponible. ¿Te ayudo con algo más?');
            }
        }

        try {
            $cita = CitaService::guardar([
                'paciente_id' => $conversacion->dato('paciente_id'),
                'medico_id' => $medico->id,
                'fecha' => $fecha,
                'hora_inicio' => $inicio,
                'hora_fin' => $fin,
            ], $citaReprogramar, 'chatbot');
        } catch (\DomainException $e) {
            // El horario se ocupó mientras el paciente confirmaba: ofrecemos los libres más cercanos
            $conversacion->olvidar('turno');

            return $this->ofrecerAlternativas($conversacion, $medico, $fecha, $inicio, "No se pudo guardar: {$e->getMessage()}");
        }

        $conversacion->terminar();
        $this->auditar(($citaReprogramar ? 'Reprogramó' : 'Agendó')." la cita #{$cita->id} por el chatbot", $cita);

        $accion = $citaReprogramar ? 'REPROGRAMADA' : 'agendada';
        $consultorio = DisponibilidadService::consultorio($medico, $fecha);

        return new Respuesta(
            "✅ ¡Listo! Tu cita N.° {$cita->id} ha sido {$accion} exitosamente para el ".date('d/m/Y', strtotime($fecha)).' a las '.date('g:i A', strtotime($inicio))
            ." con el Dr. {$medico->apellidos}".($consultorio ? " (consultorio {$consultorio})" : '').".\nEstado: {$cita->fresh()->estado_texto}. ¿Te ayudo con algo más?"
        );
    }

    private function preguntarEspecialidad(Conversacion $conversacion, string $texto): Respuesta
    {
        $conversacion->irA('especialidad');

        return new Respuesta($texto, $this->opcionesEspecialidades());
    }

    private function preguntarMedico(Conversacion $conversacion, Especialidad $especialidad, string $intro): Respuesta
    {
        $medicos = Medico::activos()->where('especialidad_id', $especialidad->id)->orderBy('apellidos')->orderBy('nombres')->get();

        if ($medicos->isEmpty()) {
            $conversacion->olvidar('especialidad_id');

            return $this->preguntarEspecialidad($conversacion, "{$intro}En este momento no hay médicos disponibles para {$especialidad->nombre}. Elige otra especialidad:");
        }

        $opciones = $medicos->map(fn (Medico $m) => ['texto' => "Dr. {$m->nombres} {$m->apellidos}", 'valor' => (string) $m->id])->all();
        $conversacion->irA('medico');
        $conversacion->guardar(['opciones' => $opciones]);

        return new Respuesta("{$intro}Para {$especialidad->nombre} tenemos a los siguientes médicos disponibles. Por favor, selecciona uno:", $opciones);
    }

    // Turnos libres de un día; se numeran y el paciente responde con el número (valor del botón)
    private function ofrecerTurnosDelDia(Conversacion $conversacion, Medico $medico, string $fecha, array $turnos, string $intro): Respuesta
    {
        $libres = [];
        $opciones = [];
        foreach (array_values($turnos) as $indice => $turno) {
            $valor = (string) ($indice + 1);
            $libres[$valor] = [$fecha, $turno['inicio'], $turno['fin']];
            $opciones[] = ['texto' => date('g:i A', strtotime($turno['inicio'])), 'valor' => $valor];
        }

        return $this->ofrecerHorarios($conversacion, $libres, $opciones, "{$intro}Para el ".date('d/m/Y', strtotime($fecha)).' tiene disponibles estos horarios:');
    }

    // Conflicto u hora ocupada: ofrece los turnos libres más cercanos al pedido (pueden ser de otro día).
    // Si el médico ya no tiene ninguno, ofrece otros médicos de la misma especialidad.
    private function ofrecerAlternativas(Conversacion $conversacion, Medico $medico, string $fecha, string $inicio, string $introduccion): Respuesta
    {
        $alternativas = DisponibilidadService::alternativas($medico, $fecha, $inicio, 3, $conversacion->dato('cita_a_reprogramar'));

        if ($alternativas) {
            $libres = [];
            $opciones = [];
            foreach ($alternativas as $indice => $turno) {
                $valor = (string) ($indice + 1);
                $libres[$valor] = [$turno['fecha'], $turno['inicio'], $turno['fin']];
                $opciones[] = ['texto' => date('d/m/Y', strtotime($turno['fecha'])).' · '.date('g:i A', strtotime($turno['inicio'])), 'valor' => $valor];
            }

            return $this->ofrecerHorarios($conversacion, $libres, $opciones, trim($introduccion)." Estos son los horarios libres más cercanos con el Dr. {$medico->apellidos}:");
        }

        $otros = Medico::activos()
            ->where('especialidad_id', $medico->especialidad_id)
            ->whereKeyNot($medico->id)
            ->orderBy('apellidos')
            ->get();

        if ($otros->isEmpty()) {
            $conversacion->olvidar('especialidad_id', 'medico_id', 'turno');

            return $this->preguntarEspecialidad($conversacion, trim($introduccion)." El Dr. {$medico->apellidos} ya no tiene horarios libres en los próximos ".DisponibilidadService::DIAS_BUSQUEDA.' días. Elige una especialidad para ver otros médicos:');
        }

        $opciones = $otros->map(fn (Medico $m) => ['texto' => "Dr. {$m->nombres} {$m->apellidos}", 'valor' => (string) $m->id])->all();
        $conversacion->olvidar('medico_id', 'turno');
        $conversacion->irA('medico');
        $conversacion->guardar(['opciones' => $opciones]);

        return new Respuesta(trim($introduccion)." El Dr. {$medico->apellidos} ya no tiene horarios libres. Puedes elegir otro médico de la especialidad:", $opciones);
    }

    private function ofrecerHorarios(Conversacion $conversacion, array $libres, array $opciones, string $texto): Respuesta
    {
        $conversacion->irA('horario');
        $conversacion->guardar(['horarios' => $libres, 'opciones' => $opciones]);

        return new Respuesta($texto, $opciones);
    }

    private function opcionesEspecialidades(): array
    {
        return Especialidad::where('estado', 1)->orderBy('nombre')->get()
            ->map(fn (Especialidad $e) => ['texto' => $e->nombre, 'valor' => $e->nombre])
            ->all();
    }

    private function opcionesResumen(): array
    {
        return [
            ['texto' => '✅ Confirmar', 'valor' => 'confirmar'],
            ['texto' => '🕒 Cambiar horario', 'valor' => 'cambiar horario'],
            ['texto' => '👨‍⚕️ Cambiar médico', 'valor' => 'cambiar medico'],
            ['texto' => '✖ Cancelar', 'valor' => 'cancelar'],
        ];
    }
}
