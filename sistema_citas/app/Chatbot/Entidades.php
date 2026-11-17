<?php

namespace App\Chatbot;

use App\Models\Especialidad;
use App\Models\Medico;

// HU09: datos que el paciente escribió en un mensaje. Fecha en Y-m-d y hora en H:i.
final readonly class Entidades
{
    public function __construct(
        public ?string $dni = null,
        public ?Especialidad $especialidad = null,
        public ?Medico $medico = null,
        public ?string $fecha = null,
        public ?string $hora = null,
    ) {}

    public function vacia(): bool
    {
        return ! $this->dni && ! $this->especialidad && ! $this->medico && ! $this->fecha && ! $this->hora;
    }
}
