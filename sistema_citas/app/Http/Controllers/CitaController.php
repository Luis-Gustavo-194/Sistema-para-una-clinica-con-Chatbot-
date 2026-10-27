<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CitaService;

class CitaController extends Controller
{
    public function agendar(Request $request)
    {
        // 1. Validamos que los datos existan y tengan el formato correcto
        $datos = $request->validate([
            'paciente_id' => 'required|integer|exists:pacientes,id',
            'medico_id' => 'required|integer|exists:medicos,id',
            'fecha' => 'required|date_format:Y-m-d|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i:s',
            'hora_fin' => 'required|date_format:H:i:s|after:hora_inicio',
        ]);

        try {
            // 2. La transacción y el control de choques viven en CitaService
            $nuevaCita = CitaService::guardar($datos);

            // Retornamos una respuesta en formato JSON (ideal para que la lea el Chatbot o el Frontend)
            return response()->json([
                'mensaje' => 'Cita agendada correctamente',
                'cita' => $nuevaCita
            ], 201);

        } catch (\DomainException $e) {
            // Choque de horario: se informa al cliente. Otros errores (BD, etc.) los maneja Laravel.
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
