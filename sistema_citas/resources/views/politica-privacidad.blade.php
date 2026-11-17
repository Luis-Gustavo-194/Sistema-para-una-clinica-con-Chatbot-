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
        <section>
            <h2 class="text-base font-semibold text-on-surface-strong">6. Correos electrónicos y servicios de Google</h2>
            <p class="mt-1">Usamos la API de Gmail de Google únicamente para <strong>enviar</strong> correos del propio sistema: enlaces para crear o restablecer tu contraseña y confirmaciones de citas. La aplicación solo solicita el permiso <code>gmail.send</code>: no lee, guarda ni comparte el contenido de ningún buzón de Gmail.</p>
            <p class="mt-1">El uso de la información recibida de las API de Google cumple la <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener" class="font-semibold text-primary underline-offset-4 hover:underline">Política de datos de usuario de los servicios de API de Google</a>, incluidos los requisitos de uso limitado. Para cualquier consulta escríbenos a {{ config('clinica.correo') }}.</p>
        </section>
    </div>

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('registro') }}" class="mt-8 inline-flex items-center gap-1 text-sm text-on-surface hover:text-primary">
        <x-icono nombre="chevron-izquierda" class="size-4" /> Volver
    </a>
@endsection
