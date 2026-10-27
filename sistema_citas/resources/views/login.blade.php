@extends('layouts.acceso')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <h1 class="text-3xl font-semibold text-primary">Acceso al sistema</h1>
    <p class="mt-2 text-on-surface">Ingresa con tu correo y contraseña.</p>

    @if ($errors->any())
        <x-ui.alerta tono="peligro" class="mt-6">{{ $errors->first() }}</x-ui.alerta>
    @endif

    <form action="/login" method="POST" class="mt-8 space-y-5">
        @csrf
        <x-ui.input nombre="correo" etiqueta="Correo electrónico" type="email" required autofocus placeholder="ejemplo@correo.com" autocomplete="email" />
        <x-ui.input nombre="contrasena" etiqueta="Contraseña" type="password" required placeholder="••••••••" autocomplete="current-password" />
        <x-ui.boton type="submit" tamano="lg" class="w-full">Iniciar sesión</x-ui.boton>
    </form>

    <div class="mt-8 rounded-radius bg-lavanda p-4 text-center text-sm">
        ¿Paciente nuevo?
        <a href="{{ route('registro') }}" class="font-semibold text-primary underline-offset-4 hover:underline">Crea tu cuenta</a>
    </div>
    <a href="/" class="mt-6 inline-flex items-center gap-1 text-sm text-on-surface hover:text-primary">
        <x-icono nombre="chevron-izquierda" class="size-4" /> Volver a la página principal
    </a>
@endsection
