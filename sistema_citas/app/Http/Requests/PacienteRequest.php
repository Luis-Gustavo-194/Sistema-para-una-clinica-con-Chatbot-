<?php

namespace App\Http\Requests;

use App\Models\Paciente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// HU05: datos de la ficha del paciente que registra o edita recepción.
// El acceso por rol lo controla el middleware 'rol:' de las rutas.
class PacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Paciente|null $paciente */
        $paciente = $this->route('paciente');

        return [
            'dni' => ['required', 'digits:8', Rule::unique('pacientes', 'dni')->ignore($paciente?->id)],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s-]{6,20}$/'],
            'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'seguro' => ['required', Rule::in(Paciente::SEGUROS)],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($paciente?->usuario_id)],
            // El consentimiento (Ley 29733) se pide al registrar; al editar ya consta su fecha
            'consentimiento' => $paciente ? ['nullable'] : ['accepted'],
        ];
    }
}
