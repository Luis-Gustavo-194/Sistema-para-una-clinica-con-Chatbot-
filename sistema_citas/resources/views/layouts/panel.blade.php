{{--
    Layout común de los paneles, con el estilo de Orchid: barra lateral oscura, breadcrumb
    y una tarjeta de cabecera con el título y las acciones de la pantalla.

    Lo extienden layouts/admin, layouts/medico y layouts/paciente, que definen:
      $menu         => [['ruta', 'activo', 'icono', 'texto', 'contador'?, 'contadorTitulo'?], ...]
      $rolNombre, $usuarioNombre, $inicioUrl, $saludoChat (null = sin chatbot)
    Cada pantalla define las secciones: titulo, subtitulo (opcional), acciones (opcional),
    migas (opcional) y contenido.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    @include('partials.head', ['titulo' => trim($__env->yieldContent('titulo'))])
</head>
<body class="min-h-screen bg-fondo-panel font-sans text-on-surface antialiased" x-data="{ menuAbierto: false }">
    @php
        $iniciales ??= collect(preg_split('/[\s@.]+/', $usuarioNombre))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
    @endphp

    <div class="flex min-h-screen">
        {{-- Fondo oscuro cuando el menú está abierto en móvil --}}
        <div x-cloak x-show="menuAbierto" x-on:click="menuAbierto = false" x-transition.opacity class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

        {{-- Barra lateral --}}
        <aside x-bind:class="menuAbierto ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-panel text-on-panel transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
            <div class="flex items-center justify-between px-6 pb-6 pt-7">
                <a href="{{ $inicioUrl }}" aria-label="Inicio del panel"><x-logo claro compacto /></a>
                <button type="button" class="text-on-panel lg:hidden" x-on:click="menuAbierto = false" aria-label="Cerrar menú">
                    <x-icono nombre="cerrar" class="size-6" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-4" aria-label="Menú del panel">
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-on-panel/50">Navegación</p>
                <ul class="space-y-1">
                    @foreach ($menu as $item)
                        @php $activo = request()->routeIs(...(array) $item['activo']); @endphp
                        <li>
                            <a href="{{ route($item['ruta']) }}" @if ($activo) aria-current="page" @endif
                               @class([
                                   'flex items-center gap-3 rounded-radius px-3 py-2.5 text-sm transition',
                                   'bg-panel-alt font-semibold text-on-panel-strong' => $activo,
                                   'hover:bg-panel-alt/60 hover:text-on-panel-strong' => !$activo,
                               ])>
                                <x-icono :nombre="$item['icono']" class="size-5" />
                                <span class="flex-1">{{ $item['texto'] }}</span>
                                @if (!empty($item['contador']))
                                    <span class="rounded-full bg-info px-2 py-0.5 text-xs font-bold text-on-info" title="{{ $item['contadorTitulo'] ?? '' }}">{{ $item['contador'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="border-t border-panel-borde p-4">
                <div class="flex items-center gap-3 rounded-radius bg-panel-alt p-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-on-primary">{{ $iniciales }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-on-panel-strong">{{ $usuarioNombre }}</p>
                        <p class="text-xs text-on-panel/70">{{ $rolNombre }}</p>
                    </div>
                    <a href="/logout" class="rounded-radius p-1.5 hover:bg-panel hover:text-on-panel-strong" title="Cerrar sesión" aria-label="Cerrar sesión">
                        <x-icono nombre="salir" class="size-5" />
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Barra superior solo en móvil --}}
            <header class="sticky top-0 z-20 flex items-center gap-3 bg-panel px-4 py-3 text-on-panel lg:hidden">
                <button type="button" x-on:click="menuAbierto = true" aria-label="Abrir menú"><x-icono nombre="menu" class="size-7" /></button>
                <x-logo claro compacto />
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                {{-- Breadcrumb --}}
                <nav class="mb-3 text-sm text-on-surface" aria-label="Ruta de navegación">
                    <ol class="flex flex-wrap items-center gap-1.5">
                        <li><a href="{{ $inicioUrl }}" class="font-medium text-on-surface-strong hover:text-primary">Inicio</a></li>
                        <li aria-hidden="true">/</li>
                        @hasSection('migas')
                            @yield('migas')
                        @else
                            <li aria-current="page">@yield('titulo')</li>
                        @endif
                    </ol>
                </nav>

                {{-- Cabecera de la pantalla --}}
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-radius bg-surface px-6 py-5 shadow-sm ring-1 ring-outline/60">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-on-surface-strong">@yield('titulo')</h1>
                        @hasSection('subtitulo')
                            <p class="mt-1 text-sm text-on-surface">@yield('subtitulo')</p>
                        @endif
                    </div>
                    @hasSection('acciones')
                        <div class="flex flex-wrap items-center gap-2">@yield('acciones')</div>
                    @endif
                </div>

                @yield('contenido')
            </main>
        </div>
    </div>

    <x-ui.toasts />
    <x-ui.modal-confirmar />
    @if (!empty($saludoChat))
        <x-chatbot :saludo="$saludoChat" :placeholder="$placeholderChat ?? 'Escribe tu mensaje...'" />
    @endif
</body>
</html>
