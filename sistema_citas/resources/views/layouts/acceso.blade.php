{{-- Pantalla dividida para login y registro: panel de marca a la izquierda y formulario a la derecha --}}
<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['titulo' => trim($__env->yieldContent('titulo'))])
</head>
<body class="min-h-screen bg-surface font-sans text-on-surface antialiased">
    <div class="grid min-h-screen lg:grid-cols-[1fr_1.1fr]">
        <aside class="relative hidden overflow-hidden bg-gradient-to-br from-primary via-[#5b2785] to-[#7a3aa8] p-12 text-white lg:flex lg:flex-col">
            <div class="absolute -right-24 -top-24 size-80 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-32 -left-20 size-96 rounded-full bg-secondary/20"></div>

            <a href="/" class="relative" aria-label="Ir al inicio"><x-logo claro /></a>

            <div class="relative my-auto max-w-md">
                <h2 class="text-4xl font-semibold leading-tight">Tu salud, <span class="font-extrabold text-[#ffb36b]">más cerca</span> y en línea.</h2>
                <ul class="mt-8 space-y-4 text-white/90">
                    <li class="flex gap-3"><x-icono nombre="check-circulo" class="size-6 shrink-0 text-[#ffb36b]" /> Agenda, reprograma o cancela tus citas cuando quieras.</li>
                    <li class="flex gap-3"><x-icono nombre="check-circulo" class="size-6 shrink-0 text-[#ffb36b]" /> Consulta tu historia clínica y tus recetas.</li>
                    <li class="flex gap-3"><x-icono nombre="check-circulo" class="size-6 shrink-0 text-[#ffb36b]" /> Conversa con nuestro asistente virtual las 24 horas.</li>
                </ul>
            </div>

            <p class="relative text-sm text-white/70">{{ config('clinica.telefonos.fijo') }} · {{ config('clinica.correo') }}</p>
        </aside>

        <main class="flex flex-col px-6 py-10 sm:px-12">
            <a href="/" class="mb-10 lg:hidden" aria-label="Ir al inicio"><x-logo /></a>
            <div class="mx-auto my-auto w-full {{ trim($__env->yieldContent('ancho')) ?: 'max-w-md' }}">
                @yield('contenido')
            </div>
        </main>
    </div>
</body>
</html>
