<?php

namespace App\Chatbot;

// Mensaje del asistente: texto, botones de opciones (muestran "texto" y envían "valor")
// y un enlace opcional (p. ej. al registro de pacientes).
final readonly class Respuesta
{
    public function __construct(
        public string $texto,
        public array $opciones = [],
        public ?array $enlace = null,
    ) {}

    public static function enlace(string $texto, string $etiqueta, string $url, array $opciones = []): self
    {
        return new self($texto, $opciones, ['texto' => $etiqueta, 'url' => $url]);
    }

    // Las claves vacías se omiten: el widget solo dibuja botones o enlaces si vienen
    public function toArray(): array
    {
        return array_filter([
            'respuesta' => $this->texto,
            'opciones' => $this->opciones,
            'enlace' => $this->enlace,
        ]);
    }
}
