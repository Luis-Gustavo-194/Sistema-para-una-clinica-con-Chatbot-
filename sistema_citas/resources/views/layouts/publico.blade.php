<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['titulo' => trim($__env->yieldContent('titulo'))])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased" x-data="{ menuMovil: false }">

    {{-- Barra superior con teléfonos --}}
    <div class="hidden border-b border-outline/60 bg-surface sm:block">
        <div class="mx-auto flex max-w-7xl items-center justify-end gap-6 px-6 py-2 text-sm font-semibold text-primary">
            <a href="tel:{{ preg_replace('/\D/', '', config('clinica.telefonos.celular')) }}" class="inline-flex items-center gap-1.5 hover:text-primary-hover">
                <x-icono nombre="celular" class="size-4" /> {{ config('clinica.telefonos.celular') }}
            </a>
            <a href="tel:{{ preg_replace('/\D/', '', config('clinica.telefonos.fijo')) }}" class="inline-flex items-center gap-1.5 hover:text-primary-hover">
                <x-icono nombre="telefono" class="size-4" /> {{ config('clinica.telefonos.fijo') }}
            </a>
        </div>
    </div>

    {{-- Cabecera con menú --}}
    <header class="sticky top-0 z-30 bg-surface/95 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
            <a href="/" aria-label="Ir al inicio"><x-logo /></a>

            <nav class="hidden items-center gap-7 text-[0.95rem] font-medium text-primary lg:flex" aria-label="Principal">
                <a href="/#especialidades" class="hover:text-acento-texto">Especialidades</a>
                <a href="{{ route('directorio') }}" class="hover:text-acento-texto">Directorio médico</a>
                <a href="/#nosotros" class="hover:text-acento-texto">Nosotros</a>
                <a href="/#contacto" class="hover:text-acento-texto">Contacto</a>

                @auth
                    <x-ui.boton :href="Auth::user()->rutaPanel()" class="rounded-full px-6">
                        <x-icono nombre="inicio" class="size-4" /> Mi panel
                    </x-ui.boton>
                @else
                    {{-- Dropdown "Reservar cita" (Penguin UI) --}}
                    <div class="relative" x-data="{ abierto: false }" x-on:keydown.esc.window="abierto = false" x-on:click.outside="abierto = false">
                        <x-ui.boton class="rounded-full px-6" x-on:click="abierto = !abierto" x-bind:aria-expanded="abierto" aria-haspopup="true">
                            Reservar cita <x-icono nombre="chevron-abajo" class="size-4" />
                        </x-ui.boton>
                        <div x-cloak x-show="abierto" x-transition.origin.top.right
                             class="absolute right-0 mt-2 w-64 overflow-hidden rounded-radius bg-surface py-2 shadow-xl ring-1 ring-outline" role="menu">
                            <button type="button" x-on:click="abierto = false; $dispatch('abrir-chat')" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-on-surface-strong hover:bg-lavanda" role="menuitem">
                                <x-icono nombre="chat" class="size-5 text-primary" /> Con el asistente virtual
                            </button>
                            <a href="/login" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface-strong hover:bg-lavanda" role="menuitem">
                                <x-icono nombre="usuario" class="size-5 text-primary" /> Desde mi cuenta
                            </a>
                            <a href="{{ route('registro') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface-strong hover:bg-lavanda" role="menuitem">
                                <x-icono nombre="mas" class="size-5 text-primary" /> Crear una cuenta
                            </a>
                        </div>
                    </div>
                @endauth
            </nav>

            <button type="button" class="rounded-radius p-2 text-primary lg:hidden" x-on:click="menuMovil = !menuMovil" x-bind:aria-expanded="menuMovil" aria-label="Abrir menú">
                <x-icono nombre="menu" class="size-7" x-show="!menuMovil" />
                <x-icono nombre="cerrar" class="size-7" x-cloak x-show="menuMovil" />
            </button>
        </div>

        {{-- Menú móvil --}}
        <nav x-cloak x-show="menuMovil" x-collapse class="border-t border-outline lg:hidden" aria-label="Principal móvil">
            <div class="flex flex-col gap-1 px-6 py-4 font-medium text-primary">
                <a href="/#especialidades" x-on:click="menuMovil = false" class="py-2">Especialidades</a>
                <a href="{{ route('directorio') }}" class="py-2">Directorio médico</a>
                <a href="/#nosotros" x-on:click="menuMovil = false" class="py-2">Nosotros</a>
                <a href="/#contacto" x-on:click="menuMovil = false" class="py-2">Contacto</a>
                @auth
                    <x-ui.boton :href="Auth::user()->rutaPanel()" class="mt-2 rounded-full">Mi panel</x-ui.boton>
                @else
                    <x-ui.boton href="/login" class="mt-2 rounded-full">Iniciar sesión</x-ui.boton>
                    <x-ui.boton :href="route('registro')" variante="secundario" class="rounded-full">Registrarse</x-ui.boton>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        @yield('contenido')
    </main>

    {{-- Pie de página --}}
    <footer id="contacto" class="bg-primary text-white/85">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 py-14 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-4">
                <x-logo claro />
                <p class="text-sm">{{ config('clinica.eslogan') }}</p>
            </div>
            <div>
                <h2 class="mb-4 font-semibold text-white">Contacto</h2>
                <ul class="space-y-2.5 text-sm">
                    <li class="flex gap-2"><x-icono nombre="ubicacion" class="size-5 shrink-0" /> {{ config('clinica.direccion') }}</li>
                    <li class="flex gap-2"><x-icono nombre="telefono" class="size-5 shrink-0" /> {{ config('clinica.telefonos.fijo') }} · {{ config('clinica.telefonos.celular') }}</li>
                    <li class="flex gap-2"><x-icono nombre="correo" class="size-5 shrink-0" /> {{ config('clinica.correo') }}</li>
                </ul>
            </div>
            <div>
                <h2 class="mb-4 font-semibold text-white">Horario de atención</h2>
                <p class="flex gap-2 text-sm"><x-icono nombre="reloj" class="size-5 shrink-0" /> {{ config('clinica.horario') }}</p>
            </div>
            <div>
                <h2 class="mb-4 font-semibold text-white">Pacientes</h2>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="/login" class="hover:text-white">Iniciar sesión</a></li>
                    <li><a href="{{ route('registro') }}" class="hover:text-white">Crear una cuenta</a></li>
                    <li><a href="/#especialidades" class="hover:text-white">Especialidades</a></li>
                    <li><a href="{{ route('directorio') }}" class="hover:text-white">Directorio médico</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/15">
            <p class="mx-auto max-w-7xl px-6 py-5 text-xs text-white/70">© {{ date('Y') }} {{ config('clinica.nombre') }}. Todos los derechos reservados.</p>
        </div>
    </footer>

    <x-ui.toasts />
    <x-chatbot />
</body>
</html>
