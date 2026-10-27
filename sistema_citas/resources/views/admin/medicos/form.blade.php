@extends('layouts.admin')

@section('titulo', $medico->exists ? 'Editar médico' : 'Nuevo médico')
@section('subtitulo', $medico->exists ? "Dr(a). {$medico->nombres} {$medico->apellidos}" : 'Registra al médico y su usuario de acceso al sistema.')

@section('migas')
    <li><a href="{{ route('admin.medicos.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Médicos</a></li>
    <li aria-hidden="true">/</li>
    <li aria-current="page">{{ $medico->exists ? 'Editar' : 'Nuevo' }}</li>
@endsection

@section('contenido')
    <form method="POST" class="max-w-3xl space-y-5"
          action="{{ $medico->exists ? route('admin.medicos.update', $medico) : route('admin.medicos.store') }}">
        @csrf
        @if ($medico->exists)
            @method('PUT')
        @endif

        <x-ui.tarjeta titulo="Datos del médico">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input nombre="nombres" etiqueta="Nombres *" :valor="$medico->nombres" maxlength="100" required autofocus />
                <x-ui.input nombre="apellidos" etiqueta="Apellidos *" :valor="$medico->apellidos" maxlength="100" required />
                <x-ui.input nombre="colegiatura" etiqueta="Colegiatura (CMP) *" :valor="$medico->colegiatura" maxlength="50" required placeholder="CMP-12345" />
                <x-ui.select nombre="especialidad_id" etiqueta="Especialidad *" required
                             :ayuda="$especialidades->isEmpty() ? 'No hay especialidades activas. Crea una primero.' : null">
                    <option value="">Selecciona…</option>
                    @foreach ($especialidades as $esp)
                        <option value="{{ $esp->id }}" @selected((string) old('especialidad_id', $medico->especialidad_id) === (string) $esp->id)>
                            {{ $esp->nombre }}{{ $esp->estado ? '' : ' (inactiva)' }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
        </x-ui.tarjeta>

        <x-ui.tarjeta titulo="Acceso al sistema">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input nombre="correo" etiqueta="Correo *" type="email" :valor="$medico->usuario->correo ?? ''" maxlength="150" required autocomplete="off" />
                </div>
                <x-ui.input nombre="contrasena" type="password" minlength="8" autocomplete="new-password" :required="!$medico->exists"
                            :etiqueta="$medico->exists ? 'Nueva contraseña' : 'Contraseña *'"
                            :ayuda="$medico->exists ? 'Déjala en blanco para mantener la actual.' : 'Mínimo 8 caracteres.'" />
                <x-ui.input nombre="contrasena_confirmation" etiqueta="Confirmar contraseña" type="password" minlength="8" autocomplete="new-password" :required="!$medico->exists" />
            </div>
        </x-ui.tarjeta>

        <div class="flex flex-wrap gap-2">
            <x-ui.boton type="submit">{{ $medico->exists ? 'Guardar cambios' : 'Registrar médico' }}</x-ui.boton>
            <x-ui.boton :href="route('admin.medicos.index')" variante="secundario">Cancelar</x-ui.boton>
        </div>
    </form>
@endsection
