@extends('layouts.acceso')

@section('titulo', 'Política de privacidad')
@section('ancho', 'max-w-2xl')

@section('contenido')
    <h1 class="text-3xl font-semibold text-primary">Política de tratamiento de datos personales</h1>
    <p class="mt-2 text-on-surface">Conforme a la Ley N.° 29733, Ley de Protección de Datos Personales, y su reglamento (D.S. N.° 016-2024-JUS).</p>

    <div class="mt-8 space-y-5 text-sm leading-relaxed text-on-surface">
        <section>
            <h2 class="text-base font-semibold text-on-surface-strong">1. Responsable del tratamiento</h2>
            <p class="mt-1">{{ config('clinica.nombre') }}, con domicilio en {{ config('clinica.direccion') }}. Contacto: {{ config('clinica.correo') }} · {{ config('clinica.telefonos.fijo') }}.</p>
        </section>
        <section>
            <h2 class="text-base font-semibold text-on-surface-strong">2. Datos que recopilamos</h2>
            <p class="mt-1">DNI, nombres y apellidos, fecha de nacimiento, teléfono, correo electrónico, seguro de salud y la información de tus citas e historia clínica.</p>
        </section>
        <section>
            <h2 class="text-base font-semibold text-on-surface-strong">3. Finalidad</h2>
            <p class="mt-1">Identificarte, gestionar tus citas médicas, registrar la atención recibida y comunicarnos contigo sobre tus citas. No usamos tus datos con fines publicitarios ni los cedemos a terceros, salvo obligación legal.</p>
        </section>
        <section>
            <h2 class="text-base font-semibold text-on-surface-strong">4. Seguridad</h2>
            <p class="mt-1">Protegemos tu cuenta con contraseña cifrada y bloqueo ante intentos fallidos. Las acciones sobre tus datos quedan registradas en una bitácora, donde tu DNI, correo y teléfono se guardan parcialmente ocultos.</p>
        </section>
        <section>
            <h2 class="text-base font-semibold text-on-surface-strong">5. Tus derechos (ARCO)</h2>
            <p class="mt-1">Puedes solicitar el acceso, rectificación, cancelación u oposición al tratamiento de tus datos escribiendo a {{ config('clinica.correo') }} o acudiendo a recepción con tu DNI.</p>
        </section>
    </div>

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('registro') }}" class="mt-8 inline-flex items-center gap-1 text-sm text-on-surface hover:text-primary">
        <x-icono nombre="chevron-izquierda" class="size-4" /> Volver
    </a>
@endsection
