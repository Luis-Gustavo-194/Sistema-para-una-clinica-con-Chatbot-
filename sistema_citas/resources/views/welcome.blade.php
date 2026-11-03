@extends('layouts.publico')

@section('titulo', 'Inicio')

@php
    $diapositivas = [
        [
            'etiqueta' => 'Novedad',
            'titulo' => 'Agenda tu cita <strong>en minutos</strong>, desde cualquier lugar',
            'texto' => 'Elige la especialidad, el médico y el horario que más te acomode. Sin colas ni llamadas.',
            'imagen' => 'img/ilustracion-agenda.svg',
            'insignia' => '24/7',
            'insigniaTexto' => 'en línea',
        ],
        [
            'etiqueta' => 'Asistente virtual',
            'titulo' => 'Conversa con nuestro <strong>asistente inteligente</strong>',
            'texto' => 'Agenda, cancela o reprograma tus citas escribiendo como lo harías con una persona.',
            'imagen' => 'img/ilustracion-asistente.svg',
            'insignia' => 'IA',
            'insigniaTexto' => 'asistente',
        ],
        [
            'etiqueta' => 'Tu salud en orden',
            'titulo' => 'Tu <strong>historia clínica</strong> siempre a la mano',
            'texto' => 'Consulta tus diagnósticos, tratamientos y recetas cada vez que lo necesites.',
            'imagen' => 'img/ilustracion-historia.svg',
            'insignia' => '100%',
            'insigniaTexto' => 'digital',
        ],
    ];

    $ayudas = [
        ['icono' => 'calendario-mas', 'titulo' => 'Reserva una cita', 'texto' => 'Elige especialista y horario disponible en pocos pasos.', 'url' => route('registro'), 'chat' => false],
        ['icono' => 'chat', 'titulo' => 'Asistente virtual', 'texto' => 'Agenda, cancela o reprograma conversando con nuestro asistente.', 'url' => null, 'chat' => true],
        ['icono' => 'corazon', 'titulo' => 'Especialidades', 'texto' => 'Conoce las áreas médicas y los especialistas que te atienden.', 'url' => '#especialidades', 'chat' => false],
        ['icono' => 'documento', 'titulo' => 'Mi historia clínica', 'texto' => 'Revisa tus atenciones, diagnósticos y recetas en línea.', 'url' => '/login', 'chat' => false],
    ];

    $novedades = [
        ['imagen' => 'img/ilustracion-asistente.svg', 'titulo' => 'Estrenamos asistente virtual para agendar tus citas', 'texto' => 'Ahora puedes reservar, cancelar o reprogramar escribiendo en el chat de nuestra web.'],
        ['imagen' => 'img/ilustracion-agenda.svg', 'titulo' => 'Agenda en línea con horarios reales de cada especialista', 'texto' => 'Ves solo los turnos libres y confirmas tu cita al instante.'],
        ['imagen' => 'img/ilustracion-historia.svg', 'titulo' => 'Historia clínica digital para todos nuestros pacientes', 'texto' => 'Tus diagnósticos y recetas quedan registrados y disponibles en tu cuenta.'],
    ];
@endphp

@section('contenido')
    {{-- ============ HERO CON CARRUSEL ============ --}}
    <section class="overflow-hidden bg-lavanda" x-data="carrusel({{ count($diapositivas) }})" x-on:mouseenter="pausar()" x-on:mouseleave="reanudar()" aria-roledescription="carrusel" aria-label="Novedades de la clínica">
        <div class="relative mx-auto max-w-7xl px-6 py-14 lg:py-20">
            @foreach ($diapositivas as $indice => $diapositiva)
                <div x-show="actual === {{ $indice }}" @if ($indice > 0) x-cloak @endif
                     x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
                     class="grid items-center gap-12 lg:grid-cols-[1.25fr_1fr]" aria-roledescription="diapositiva" aria-label="{{ $indice + 1 }} de {{ count($diapositivas) }}">
                    <div class="pl-6 sm:pl-10">
                        <p class="hero-curva text-xl font-extrabold uppercase tracking-wide text-acento-texto">{{ $diapositiva['etiqueta'] }}</p>
                        <h1 class="mt-8 text-4xl font-medium leading-tight text-primary sm:text-5xl lg:text-[3.4rem] [&_strong]:font-extrabold">{!! $diapositiva['titulo'] !!}</h1>
                        <p class="mt-6 max-w-xl text-lg text-primary/80">{{ $diapositiva['texto'] }}</p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <x-ui.boton :href="route('registro')" tamano="lg" class="rounded-full">Reservar cita</x-ui.boton>
                            <x-ui.boton variante="secundario" tamano="lg" class="rounded-full" x-on:click="$dispatch('abrir-chat')">
                                <x-icono nombre="chat" class="size-5" /> Hablar con el asistente
                            </x-ui.boton>
                        </div>
                    </div>
                    <div class="relative mx-auto w-full max-w-md">
                        <div class="rounded-[2rem] bg-white p-4 shadow-2xl shadow-primary/15">
                            <img src="{{ asset($diapositiva['imagen']) }}" alt="" class="w-full rounded-[1.5rem]">
                        </div>
                        <div class="absolute -bottom-6 -right-4 flex size-32 flex-col items-center justify-center rounded-full bg-secondary text-on-secondary shadow-xl ring-8 ring-white/70 sm:-right-8">
                            <span class="text-3xl font-extrabold">{{ $diapositiva['insignia'] }}</span>
                            <span class="text-xs font-semibold uppercase tracking-wider">{{ $diapositiva['insigniaTexto'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Puntos indicadores --}}
            <div class="mt-12 flex justify-center gap-2.5">
                @foreach ($diapositivas as $indice => $diapositiva)
                    <button type="button" x-on:click="ir({{ $indice }})" class="h-2.5 rounded-full transition-all"
                            x-bind:class="actual === {{ $indice }} ? 'w-8 bg-primary' : 'w-2.5 bg-primary/25 hover:bg-primary/40'"
                            aria-label="Ver diapositiva {{ $indice + 1 }}"></button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ ¿EN QUÉ PODEMOS AYUDARTE? ============ --}}
    <section class="mx-auto max-w-7xl px-6 py-20">
        <h2 class="text-center text-4xl font-medium text-primary sm:text-5xl">¿En qué podemos ayudarte hoy?</h2>
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($ayudas as $ayuda)
                @php $claseTarjeta = 'group flex h-full flex-col items-center rounded-3xl border border-outline bg-surface px-6 py-10 text-center transition hover:-translate-y-1 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/10'; @endphp
                @if ($ayuda['chat'])
                    <button type="button" x-on:click="$dispatch('abrir-chat')" class="{{ $claseTarjeta }}">
                @else
                    <a href="{{ $ayuda['url'] }}" class="{{ $claseTarjeta }}">
                @endif
                    <span class="flex size-20 items-center justify-center rounded-full bg-lavanda text-primary transition group-hover:bg-primary group-hover:text-on-primary">
                        <x-icono :nombre="$ayuda['icono']" class="size-10" />
                    </span>
                    <span class="mt-6 text-xl font-semibold text-primary">{{ $ayuda['titulo'] }}</span>
                    <span class="mt-3 text-sm text-on-surface">{{ $ayuda['texto'] }}</span>
                    <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-acento-texto">Ir ahora <x-icono nombre="flecha-derecha" class="size-4" /></span>
                @if ($ayuda['chat'])
                    </button>
                @else
                    </a>
                @endif
            @endforeach
        </div>
    </section>

    {{-- ============ ESPECIALIDADES (desde la BD) ============ --}}
    <section id="especialidades" class="scroll-mt-28 bg-surface-alt py-20">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wider text-acento-texto">Nuestras áreas</p>
                    <h2 class="mt-2 text-4xl font-medium text-primary">Especialidades</h2>
                </div>
                <x-ui.boton :href="route('registro')" variante="secundario" class="rounded-full">Agendar con un especialista</x-ui.boton>
            </div>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($especialidades as $especialidad)
                    <article class="flex gap-4 rounded-2xl bg-surface p-6 shadow-sm ring-1 ring-outline/60">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-lavanda text-primary"><x-icono nombre="corazon" class="size-6" /></span>
                        <div>
                            <h3 class="text-lg font-semibold text-primary">{{ $especialidad->nombre }}</h3>
                            <p class="mt-1 text-sm text-on-surface">{{ $especialidad->descripcion ?: 'Atención especializada con profesionales de nuestra clínica.' }}</p>
                            <p class="mt-3 text-xs font-semibold text-acento-texto">{{ $especialidad->medicos_activos_count }} {{ $especialidad->medicos_activos_count === 1 ? 'médico' : 'médicos' }}</p>
                        </div>
                    </article>
                @empty
                    <p class="text-on-surface">Pronto publicaremos nuestras especialidades.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============ STAFF MÉDICO (desde la BD) ============ --}}
    <section id="staff" class="scroll-mt-28 mx-auto max-w-7xl px-6 py-20">
        <p class="text-sm font-bold uppercase tracking-wider text-acento-texto">Profesionales</p>
        <h2 class="mt-2 text-4xl font-medium text-primary">Staff médico</h2>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($medicos as $medico)
                <article class="flex flex-col items-center rounded-2xl border border-outline bg-surface p-6 text-center">
                    <span class="flex size-20 items-center justify-center rounded-full bg-primary text-2xl font-bold text-on-primary ring-4 ring-lavanda">
                        {{ mb_strtoupper(mb_substr($medico->nombres, 0, 1) . mb_substr($medico->apellidos, 0, 1)) }}
                    </span>
                    <h3 class="mt-4 font-semibold text-on-surface-strong">Dr(a). {{ $medico->nombres }} {{ $medico->apellidos }}</h3>
                    <p class="text-sm text-primary">{{ $medico->especialidad->nombre }}</p>
                    <p class="mt-1 text-xs text-on-surface">CMP {{ preg_replace('/^CMP[-\s]*/i', '', $medico->colegiatura) }}</p>
                </article>
            @empty
                <p class="text-on-surface">Pronto publicaremos a nuestro staff médico.</p>
            @endforelse
        </div>
        @if ($medicos->isNotEmpty())
            <div class="mt-8 text-center">
                <x-ui.boton :href="route('directorio')" variante="secundario" class="rounded-full">Ver el directorio con horarios de atención <x-icono nombre="flecha-derecha" class="size-4" /></x-ui.boton>
            </div>
        @endif
    </section>

    {{-- ============ NOSOTROS: CIFRAS ============ --}}
    <section id="nosotros" class="scroll-mt-28 bg-primary py-16 text-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 lg:grid-cols-[1fr_1.4fr] lg:items-center">
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-[#ffb36b]">Nosotros</p>
                <h2 class="mt-2 text-3xl font-semibold sm:text-4xl">{{ config('clinica.nombre') }}</h2>
                <p class="mt-4 text-white/80">{{ config('clinica.eslogan') }}. Unimos la experiencia de nuestros especialistas con herramientas digitales para que atenderte sea simple.</p>
            </div>
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach (['especialidades' => 'Especialidades', 'medicos' => 'Médicos', 'pacientes' => 'Pacientes', 'atenciones' => 'Atenciones'] as $clave => $texto)
                    <div class="rounded-2xl bg-white/10 p-5 text-center ring-1 ring-white/15">
                        <dt class="text-sm text-white/75">{{ $texto }}</dt>
                        <dd class="mt-1 text-3xl font-extrabold">{{ $cifras[$clave] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ============ CONVENIOS (carrusel) ============ --}}
    <section class="mx-auto max-w-7xl px-6 py-20" x-data>
        <h2 class="text-4xl font-medium text-primary">Convenios</h2>
        <div class="relative mt-10 flex items-center gap-3">
            <button type="button" x-on:click="$refs.carril.scrollBy({ left: -320, behavior: 'smooth' })" class="hidden shrink-0 rounded-full p-2 text-primary hover:bg-lavanda sm:block" aria-label="Convenios anteriores">
                <x-icono nombre="chevron-izquierda" class="size-7" />
            </button>
            <div x-ref="carril" class="carril-horizontal flex min-w-0 flex-1 gap-6 overflow-x-auto px-1 py-4">
                @foreach (config('clinica.convenios') as $convenio)
                    <div class="flex w-64 shrink-0 flex-col items-center justify-center gap-1 rounded-2xl bg-surface px-6 py-7 text-center shadow-lg shadow-primary/5 ring-1 ring-outline/60">
                        <x-icono nombre="escudo" class="size-8 text-secondary" />
                        <p class="mt-2 text-lg font-bold text-primary">{{ $convenio['nombre'] }}</p>
                        <p class="text-xs text-on-surface">{{ $convenio['detalle'] }}</p>
                    </div>
                @endforeach
            </div>
            <button type="button" x-on:click="$refs.carril.scrollBy({ left: 320, behavior: 'smooth' })" class="hidden shrink-0 rounded-full p-2 text-primary hover:bg-lavanda sm:block" aria-label="Más convenios">
                <x-icono nombre="chevron-derecha" class="size-7" />
            </button>
        </div>
    </section>

    {{-- ============ NOVEDADES ============ --}}
    <section class="bg-surface-alt py-20">
        <div class="mx-auto max-w-7xl px-6">
            <h2 class="text-4xl font-medium text-primary">Buenas noticias</h2>
            <div class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ($novedades as $novedad)
                    <article class="overflow-hidden rounded-3xl bg-surface shadow-sm ring-1 ring-outline/60 transition hover:shadow-xl hover:shadow-primary/10">
                        <img src="{{ asset($novedad['imagen']) }}" alt="" class="aspect-[4/3] w-full object-cover">
                        <div class="p-6 text-center">
                            <h3 class="text-lg font-medium leading-snug text-primary">{{ $novedad['titulo'] }}</h3>
                            <p class="mt-2 text-sm text-on-surface">{{ $novedad['texto'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
