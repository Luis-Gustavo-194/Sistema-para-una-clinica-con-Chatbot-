@extends('layouts.acceso')

@section('titulo', 'Registro de pacientes')
@section('ancho', 'max-w-xl')

@section('contenido')
    <h1 class="text-3xl font-semibold text-primary">Crear cuenta de paciente</h1>
    <p class="mt-2 text-on-surface">Regístrate para agendar y gestionar tus citas en línea.</p>

    {{-- HU02: validación en línea (resources/js/validacion.js); el servidor vuelve a validar --}}
    <form action="{{ route('registro') }}" method="POST" class="mt-8 space-y-5" x-data="validacionEnLinea" novalidate>
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.input nombre="dni" etiqueta="DNI *" inputmode="numeric" maxlength="8" pattern="\d{8}" data-mensaje="El DNI debe tener 8 dígitos." required placeholder="8 dígitos" autocomplete="off" />
            <x-ui.input nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento *" type="date" required min="1900-01-02" :max="today()->subDay()->toDateString()" />
            <x-ui.input nombre="nombres" etiqueta="Nombres *" maxlength="100" required autocomplete="given-name" />
            <x-ui.input nombre="apellidos" etiqueta="Apellidos *" maxlength="100" required autocomplete="family-name" />
            <x-ui.input nombre="telefono" etiqueta="Teléfono" type="tel" maxlength="20" pattern="[0-9+\s\-]{6,20}" data-mensaje="Usa solo números (6 a 20 dígitos)." placeholder="Opcional" autocomplete="tel" />
            <x-ui.select nombre="seguro" etiqueta="Seguro *" required>
                <option value="">Selecciona…</option>
                @foreach (\App\Models\Paciente::SEGUROS as $seguro)
                    <option value="{{ $seguro }}" @selected(old('seguro') === $seguro)>{{ $seguro }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <x-ui.input nombre="correo" etiqueta="Correo electrónico *" type="email" maxlength="150" required placeholder="tucorreo@ejemplo.com" autocomplete="email" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.input nombre="contrasena" etiqueta="Contraseña *" type="password" minlength="8" required autocomplete="new-password" ayuda="Mínimo 8 caracteres." />
            <x-ui.input nombre="contrasena_confirmation" etiqueta="Confirmar contraseña *" type="password" minlength="8" required autocomplete="new-password" data-coincide="contrasena" />
        </div>
        <x-ui.casilla nombre="consentimiento" required>
            Acepto el tratamiento de mis datos personales conforme a la Ley N.° 29733, Ley de Protección de Datos Personales, y la
            <a href="{{ route('politica-privacidad') }}" target="_blank" class="font-semibold text-primary underline-offset-4 hover:underline">política de privacidad</a> de la clínica.
        </x-ui.casilla>
        <x-ui.boton type="submit" tamano="lg" class="w-full">Crear mi cuenta</x-ui.boton>
    </form>

    <p class="mt-6 text-center text-sm text-on-surface">
        ¿Ya tienes cuenta? <a href="/login" class="font-semibold text-primary hover:underline">Inicia sesión</a>
        · <a href="/" class="hover:text-primary">Volver al inicio</a>
    </p>
@endsection
