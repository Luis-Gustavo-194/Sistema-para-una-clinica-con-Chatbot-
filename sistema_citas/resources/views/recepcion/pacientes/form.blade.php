@extends('layouts.recepcion')

@section('titulo', $paciente->exists ? 'Editar ficha del paciente' : 'Registrar paciente')
@section('subtitulo', $paciente->exists ? "{$paciente->nombres} {$paciente->apellidos} · DNI {$paciente->dni}" : 'El paciente recibirá un correo para crear su contraseña y acceder al sistema.')

@section('migas')
    <li><a href="{{ route('recepcion.pacientes.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Pacientes</a></li>
    <li aria-hidden="true">/</li>
    <li aria-current="page">{{ $paciente->exists ? 'Editar' : 'Nuevo' }}</li>
@endsection

@section('contenido')
    <form method="POST" class="max-w-3xl space-y-5" x-data="validacionEnLinea" novalidate
          action="{{ $paciente->exists ? route('recepcion.pacientes.update', $paciente) : route('recepcion.pacientes.store') }}">
        @csrf
        @if ($paciente->exists)
            @method('PUT')
        @endif

        <x-ui.tarjeta titulo="Datos personales">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input nombre="dni" etiqueta="DNI *" :valor="$paciente->dni" inputmode="numeric" maxlength="8" pattern="\d{8}" data-mensaje="El DNI debe tener 8 dígitos." required :autofocus="!$paciente->exists" />
                <x-ui.input nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento *" type="date" :valor="$paciente->fecha_nacimiento" required min="1900-01-02" :max="today()->subDay()->toDateString()" />
                <x-ui.input nombre="nombres" etiqueta="Nombres *" :valor="$paciente->nombres" maxlength="100" required />
                <x-ui.input nombre="apellidos" etiqueta="Apellidos *" :valor="$paciente->apellidos" maxlength="100" required />
                <x-ui.input nombre="telefono" etiqueta="Teléfono" type="tel" :valor="$paciente->telefono" maxlength="20" pattern="[0-9+\s\-]{6,20}" data-mensaje="Usa solo números (6 a 20 dígitos)." placeholder="Opcional" />
                <x-ui.select nombre="seguro" etiqueta="Seguro *" required>
                    <option value="">Selecciona…</option>
                    @foreach (\App\Models\Paciente::SEGUROS as $seguro)
                        <option value="{{ $seguro }}" @selected(old('seguro', $paciente->seguro) === $seguro)>{{ $seguro }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </x-ui.tarjeta>

        <x-ui.tarjeta titulo="Acceso al sistema">
            <x-ui.input nombre="correo" etiqueta="Correo electrónico *" type="email" :valor="$paciente->usuario->correo ?? ''" maxlength="150" required autocomplete="off"
                        :ayuda="$paciente->exists ? null : 'Le enviaremos aquí el enlace para crear su contraseña.'" />
        </x-ui.tarjeta>

        @unless ($paciente->exists)
            <x-ui.tarjeta titulo="Protección de datos personales">
                <x-ui.casilla nombre="consentimiento" required>
                    El paciente autoriza el tratamiento de sus datos personales conforme a la Ley N.° 29733 y la
                    <a href="{{ route('politica-privacidad') }}" target="_blank" class="font-semibold text-primary underline-offset-4 hover:underline">política de privacidad</a> de la clínica.
                </x-ui.casilla>
            </x-ui.tarjeta>
        @endunless

        <div class="flex flex-wrap gap-2">
            <x-ui.boton type="submit">{{ $paciente->exists ? 'Guardar cambios' : 'Registrar paciente' }}</x-ui.boton>
            <x-ui.boton :href="$paciente->exists ? route('recepcion.pacientes.show', $paciente) : route('recepcion.pacientes.index')" variante="secundario">Cancelar</x-ui.boton>
        </div>
    </form>
@endsection
