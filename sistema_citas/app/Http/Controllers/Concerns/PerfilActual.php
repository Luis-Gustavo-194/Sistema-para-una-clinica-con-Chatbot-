<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use App\Models\Cita;
use App\Models\Medico;
use App\Models\Paciente;

// Obtiene la ficha (médico o paciente) del usuario logueado y protege
// que solo pueda operar sobre sus propias citas.
trait PerfilActual
{
    protected function medicoActual(): Medico
    {
        return Medico::with('especialidad')->where('usuario_id', Auth::id())->first()
            ?? abort(403, 'Tu usuario no tiene un perfil de médico asociado.');
    }

    protected function pacienteActual(): Paciente
    {
        return Paciente::where('usuario_id', Auth::id())->first()
            ?? abort(403, 'Tu usuario no tiene una ficha de paciente asociada.');
    }

    // 404 si la cita no pertenece al médico logueado (no revelamos que existe)
    protected function citaDelMedico(Cita $cita, Medico $medico): Cita
    {
        abort_unless((int) $cita->medico_id === (int) $medico->id, 404);
        return $cita;
    }

    // 404 si la cita no pertenece al paciente logueado
    protected function citaDelPaciente(Cita $cita, Paciente $paciente): Cita
    {
        abort_unless((int) $cita->paciente_id === (int) $paciente->id, 404);
        return $cita;
    }
}
