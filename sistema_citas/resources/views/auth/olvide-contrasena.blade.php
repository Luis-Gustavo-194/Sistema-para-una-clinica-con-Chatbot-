@extends('layouts.acceso')

@section('titulo', 'Olvidé mi contraseña')

@section('contenido')
    <h1 class="text-3xl font-semibold text-primary">¿Olvidaste tu contraseña?</h1>
    <p class="mt-2 text-on-surface">Escribe el correo de tu cuenta y te enviaremos un enlace para crear una nueva.</p>

    @if (session('exito'))
        <x-ui.alerta tono="exito" class="mt-6">{{ session('exito') }}</x-ui.alerta>
    @endif

    <form action="{{ route('password.email') }}" method="POST" class="mt-8 space-y-5">
        @csrf
        <x-ui.input nombre="correo" etiqueta="Correo electrónico" type="email" required autofocus placeholder="ejemplo@correo.com" autocomplete="email" />
        <x-ui.boton type="submit" tamano="lg" class="w-full">Enviar enlace</x-ui.boton>
    </form>

    <a href="{{ route('login') }}" class="mt-6 inline-flex items-center gap-1 text-sm text-on-surface hover:text-primary">
        <x-icono nombre="chevron-izquierda" class="size-4" /> Volver a iniciar sesión
    </a>
@endsection
