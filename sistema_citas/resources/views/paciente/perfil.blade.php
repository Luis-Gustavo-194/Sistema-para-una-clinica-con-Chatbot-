@extends('layouts.paciente')

@section('titulo', 'Mi perfil')
@section('subtitulo', 'Si algún dato personal es incorrecto, comunícate con la clínica para corregirlo.')

@section('contenido')
    <div class="grid max-w-4xl gap-5">
        <x-ui.tarjeta titulo="Datos personales">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-xs uppercase tracking-wide">DNI</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->dni }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Fecha de nacimiento</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->fecha_nacimiento ? \Carbon\Carbon::parse($paciente->fecha_nacimiento)->format('d/m/Y') . ' (' . $paciente->edad . ' años)' : '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Nombres</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->nombres }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide">Apellidos</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $paciente->apellidos }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wide">Correo de acceso</dt><dd class="mt-0.5 font-semibold text-on-surface-strong">{{ $usuario->correo }}</dd></div>
            </dl>
        </x-ui.tarjeta>

        <div class="grid gap-5 lg:grid-cols-2">
            <x-ui.tarjeta titulo="Contacto">
                <form method="POST" action="{{ route('paciente.perfil.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <x-ui.input nombre="telefono" etiqueta="Teléfono" type="tel" maxlength="20" :valor="$paciente->telefono" placeholder="987654321" />
                    <x-ui.boton type="submit">Guardar teléfono</x-ui.boton>
                </form>
            </x-ui.tarjeta>

            <x-ui.tarjeta titulo="Cambiar contraseña">
                <form method="POST" action="{{ route('paciente.perfil.contrasena') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <x-ui.input nombre="contrasena_actual" etiqueta="Contraseña actual" type="password" required autocomplete="current-password" />
                    <x-ui.input nombre="contrasena" etiqueta="Nueva contraseña" type="password" required minlength="8" autocomplete="new-password" />
                    <x-ui.input nombre="contrasena_confirmation" etiqueta="Confirmar nueva contraseña" type="password" required minlength="8" autocomplete="new-password" />
                    <x-ui.boton type="submit">Cambiar contraseña</x-ui.boton>
                </form>
            </x-ui.tarjeta>
        </div>
    </div>
@endsection
