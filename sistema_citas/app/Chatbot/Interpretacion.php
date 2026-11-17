<?php

namespace App\Chatbot;

// Resultado del servicio de intenciones. decision: 'aceptada', 'aclarar' o 'desconocida'.
final readonly class Interpretacion
{
    const ACEPTADA = 'aceptada';

    const ACLARAR = 'aclarar';

    const DESCONOCIDA = 'desconocida';

    public function __construct(
        public string $intencion,
        public ?float $confianza,
        public array $candidatas,
        public string $decision,
    ) {}
}
