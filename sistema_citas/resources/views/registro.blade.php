@extends('layouts.acceso')

@section('titulo', 'Registro de pacientes')
@section('ancho', 'max-w-xl')

@section('contenido')
    <h1 class="text-3xl font-semibold text-primary">Crear cuenta de paciente</h1>
    <p class="mt-2 text-on-surface">Regístrate para agendar y gestionar tus citas en línea.</p>

    <form action="{{ route('registro') }}" method="POST" class="mt-8 space-y-5">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.input nombre="dni" etiqueta="DNI *" inputmode="numeric" maxlength="8" required placeholder="8 dígitos" />
            <x-ui.input nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento *" type="date" required :max="today()->subDay()->toDateString()" />
            <x-ui.input nombre="nombres" etiqueta="Nombres *" maxlength="100" required />
            <x-ui.input nombre="apellidos" etiqueta="Apellidos *" maxlength="100" required />
        </div>
        <x-ui.input nombre="telefono" etiqueta="Teléfono" type="tel" maxlength="20" placeholder="Opcional" />
        <x-ui.input nombre="correo" etiqueta="Correo electrónico *" type="email" maxlength="150" required placeholder="tucorreo@ejemplo.com" autocomplete="email" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.input nombre="contrasena" etiqueta="Contraseña *" type="password" minlength="8" required autocomplete="new-password" ayuda="Mínimo 8 caracteres." />
            <x-ui.input nombre="contrasena_confirmation" etiqueta="Confirmar contraseña *" type="password" minlength="8" required autocomplete="new-password" />
        </div>
        <x-ui.boton type="submit" tamano="lg" class="w-full">Crear mi cuenta</x-ui.boton>
    </form>

    <p class="mt-6 text-center text-sm text-on-surface">
        ¿Ya tienes cuenta? <a href="/login" class="font-semibold text-primary hover:underline">Inicia sesión</a>
        · <a href="/" class="hover:text-primary">Volver al inicio</a>
    </p>
@endsection
