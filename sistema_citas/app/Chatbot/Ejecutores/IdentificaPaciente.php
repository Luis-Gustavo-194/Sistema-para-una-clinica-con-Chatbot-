<?php

namespace App\Chatbot\Ejecutores;

use App\Chatbot\Respuesta;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Rol;
use Illuminate\Support\Facades\Auth;

// Identificación del paciente (sesión iniciada o DNI) y bitácora de las acciones del chatbot
trait IdentificaPaciente
{
    // Ficha del paciente con sesión iniciada (rol Paciente y activo), o null si no hay
    protected function pacienteLogueado(): ?Paciente
    {
        $usuario = Auth::user();
        if (! $usuario || (int) $usuario->rol_id !== Rol::PACIENTE || (int) $usuario->estado !== 1) {
            return null;
        }

        return Paciente::where('usuario_id', $usuario->id)->first();
    }

    // HU02: valida el DNI; si no está registrado, ofrece el enlace al registro y deja reintentar
    protected function buscarPaciente(?string $dni): Paciente|Respuesta
    {
        if (! $dni) {
            return new Respuesta('Ese no parece un DNI válido. Escribe solo los 8 números.');
        }

        return Paciente::where('dni', $dni)->first() ?? Respuesta::enlace(
            "No encontré ningún paciente registrado con el DNI {$dni}. Revisa que esté bien escrito y vuelve a enviarlo o, si aún no tienes cuenta, regístrate aquí:",
            'Crear mi cuenta',
            route('registro'),
        );
    }

    // HU18: bitácora con canal 'chatbot'. Sin sesión iniciada, el autor es el paciente identificado por DNI.
    protected function auditar(string $accion, Cita $cita): void
    {
        Auditoria::registrar($accion, 'citas', $cita->id, Auditoria::CANAL_CHATBOT, Auth::id() ?? $cita->paciente?->usuario_id);
    }
}
