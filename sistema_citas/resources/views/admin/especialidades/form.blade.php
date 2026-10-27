@extends('layouts.admin')

@section('titulo', $especialidad->exists ? 'Editar especialidad' : 'Nueva especialidad')
@section('subtitulo', $especialidad->exists ? $especialidad->nombre : 'Registra un área médica nueva.')

@section('migas')
    <li><a href="{{ route('admin.especialidades.index') }}" class="font-medium text-on-surface-strong hover:text-primary">Especialidades</a></li>
    <li aria-hidden="true">/</li>
    <li aria-current="page">{{ $especialidad->exists ? 'Editar' : 'Nueva' }}</li>
@endsection

@section('contenido')
    <x-ui.tarjeta class="max-w-2xl">
        <form method="POST" class="space-y-5"
              action="{{ $especialidad->exists ? route('admin.especialidades.update', $especialidad) : route('admin.especialidades.store') }}">
            @csrf
            @if ($especialidad->exists)
                @method('PUT')
            @endif

            <x-ui.input nombre="nombre" etiqueta="Nombre *" :valor="$especialidad->nombre" maxlength="100" required autofocus />
            <x-ui.textarea nombre="descripcion" etiqueta="Descripción" :valor="$especialidad->descripcion" maxlength="1000" ayuda="Opcional. Máximo 1000 caracteres." />

            <div class="flex flex-wrap gap-2 pt-2">
                <x-ui.boton type="submit">{{ $especialidad->exists ? 'Guardar cambios' : 'Crear especialidad' }}</x-ui.boton>
                <x-ui.boton :href="route('admin.especialidades.index')" variante="secundario">Cancelar</x-ui.boton>
            </div>
        </form>
    </x-ui.tarjeta>
@endsection
