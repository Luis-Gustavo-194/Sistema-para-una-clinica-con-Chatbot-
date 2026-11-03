@extends('layouts.admin')

@section('titulo', 'Datos de la clínica')
@section('subtitulo', 'Se muestran en el portal, el directorio de médicos, la política de privacidad y los correos que envía el sistema.')

@section('contenido')
    <form method="POST" action="{{ route('admin.clinica.update') }}" class="max-w-3xl space-y-5">
        @csrf
        @method('PUT')

        <x-ui.tarjeta titulo="Identidad">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input nombre="nombre" etiqueta="Nombre *" :valor="$valores['nombre']" maxlength="100" required />
                <x-ui.input nombre="ciudad" etiqueta="Ciudad *" :valor="$valores['ciudad']" maxlength="60" required />
                <div class="sm:col-span-2"><x-ui.input nombre="eslogan" etiqueta="Eslogan" :valor="$valores['eslogan']" maxlength="150" /></div>
            </div>
        </x-ui.tarjeta>

        <x-ui.tarjeta titulo="Contacto y atención">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><x-ui.input nombre="direccion" etiqueta="Dirección *" :valor="$valores['direccion']" maxlength="150" required /></div>
                <x-ui.input nombre="telefono_fijo" etiqueta="Teléfono fijo" type="tel" :valor="$valores['telefono_fijo']" maxlength="20" />
                <x-ui.input nombre="telefono_celular" etiqueta="Celular" type="tel" :valor="$valores['telefono_celular']" maxlength="20" />
                <div class="sm:col-span-2"><x-ui.input nombre="correo" etiqueta="Correo de contacto *" type="email" :valor="$valores['correo']" maxlength="150" required /></div>
                <div class="sm:col-span-2"><x-ui.input nombre="horario" etiqueta="Horario de atención *" :valor="$valores['horario']" maxlength="150" required placeholder="Lunes a viernes de 8:00 a 20:00" /></div>
            </div>
        </x-ui.tarjeta>

        <x-ui.boton type="submit">Guardar datos</x-ui.boton>
    </form>
@endsection
