@extends('layouts.acceso')

@section('titulo', 'Nueva contraseña')

@section('contenido')
    <h1 class="text-3xl font-semibold text-primary">Crea tu nueva contraseña</h1>
    <p class="mt-2 text-on-surface">Elige una contraseña de al menos 8 caracteres.</p>

    <form action="{{ route('password.update') }}" method="POST" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-ui.input nombre="correo" etiqueta="Correo electrónico" type="email" :valor="$correo" required autocomplete="email" />
        <x-ui.input nombre="contrasena" etiqueta="Nueva contraseña" type="password" minlength="8" required autofocus autocomplete="new-password" ayuda="Mínimo 8 caracteres." />
        <x-ui.input nombre="contrasena_confirmation" etiqueta="Confirmar contraseña" type="password" minlength="8" required autocomplete="new-password" />
        <x-ui.boton type="submit" tamano="lg" class="w-full">Guardar contraseña</x-ui.boton>
    </form>

    <a href="{{ route('password.request') }}" class="mt-6 inline-flex items-center gap-1 text-sm text-on-surface hover:text-primary">
        <x-icono nombre="chevron-izquierda" class="size-4" /> Pedir un enlace nuevo
    </a>
@endsection
