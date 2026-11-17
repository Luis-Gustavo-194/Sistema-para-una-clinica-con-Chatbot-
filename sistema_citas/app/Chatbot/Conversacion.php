<?php

namespace App\Chatbot;

use Illuminate\Contracts\Session\Session;

// Estado de la conversación en la sesión del navegador (no en la IP, así dos personas
// en la misma red no comparten el chat). "flujo" es la intención en curso (agendar_cita,
// cancelar_cita, reprogramar_cita), "paso" la pregunta pendiente y "datos" lo que ya se sabe.
class Conversacion
{
    public function __construct(private Session $sesion) {}

    public function flujo(): ?string
    {
        return $this->sesion->get('chat.flujo');
    }

    public function paso(): ?string
    {
        return $this->sesion->get('chat.paso');
    }

    public function enProceso(): bool
    {
        return $this->flujo() !== null;
    }

    // Empieza un flujo desde cero (se olvidan los datos anteriores)
    public function iniciar(string $flujo, ?string $paso = null): void
    {
        $this->sesion->put('chat', ['flujo' => $flujo, 'paso' => $paso, 'datos' => []]);
    }

    // Pasa a otro flujo conservando los datos (reprogramar → agendar)
    public function cambiarFlujo(string $flujo): void
    {
        $this->sesion->put('chat.flujo', $flujo);
    }

    public function irA(string $paso): void
    {
        $this->sesion->put('chat.paso', $paso);
    }

    public function dato(string $clave, mixed $defecto = null): mixed
    {
        return $this->sesion->get("chat.datos.{$clave}", $defecto);
    }

    public function guardar(array $datos): void
    {
        foreach ($datos as $clave => $valor) {
            $this->sesion->put("chat.datos.{$clave}", $valor);
        }
    }

    public function olvidar(string ...$claves): void
    {
        foreach ($claves as $clave) {
            $this->sesion->forget("chat.datos.{$clave}");
        }
    }

    public function terminar(): void
    {
        $this->sesion->forget('chat');
    }

    // Intenciones ofrecidas como botones ("¿Quisiste decir…?" o menú): al pulsar una no se consulta a Python
    public function intencionesOfrecidas(): array
    {
        return $this->sesion->get('chat_aclaracion', []);
    }

    public function ofrecerIntenciones(array $intenciones): void
    {
        $this->sesion->put('chat_aclaracion', $intenciones);
    }

    public function olvidarIntencionesOfrecidas(): void
    {
        $this->sesion->forget('chat_aclaracion');
    }
}
