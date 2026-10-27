{{-- Iconos de línea (SVG propio, 24x24). Uso: <x-icono nombre="calendario" class="size-5" /> --}}
@props(['nombre'])

@php
    $trazos = [
        'inicio' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9v11.5h4.5v-6h4v6h4.5V9"/>',
        'calendario' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
        'calendario-mas' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4M12 12.5v5M9.5 15h5"/>',
        'usuario' => '<circle cx="12" cy="8" r="4"/><path d="M4 20.5c.8-4 4-6 8-6s7.2 2 8 6"/>',
        'usuarios' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14c2.4 0 4 1.5 4.5 4"/>',
        'grafico' => '<path d="M3 20.5h18"/><rect x="5" y="11" width="3" height="7" rx=".5"/><rect x="10.5" y="6" width="3" height="12" rx=".5"/><rect x="16" y="13" width="3" height="5" rx=".5"/>',
        'reloj' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'documento' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
        'salir' => '<path d="M14 4h4.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H14"/><path d="m10 8-4 4 4 4M6 12h10"/>',
        'mas' => '<path d="M12 5v14M5 12h14"/>',
        'chat' => '<path d="M4.5 5.5h15a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H10l-4.5 3.5v-3.5h-1a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z"/><path d="M8 10h8M8 13h5"/>',
        'corazon' => '<path d="M12 20s-7.5-4.6-7.5-10.1a4.2 4.2 0 0 1 7.5-2.6 4.2 4.2 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>',
        'medico' => '<path d="M6 3v5a4 4 0 0 0 8 0V3"/><path d="M10 12v3a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="11" r="2"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'cerrar' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'telefono' => '<path d="M5 4h3.5l1.5 4-2 1.5a11 11 0 0 0 6.5 6.5l1.5-2 4 1.5V19a1.5 1.5 0 0 1-1.6 1.5C10.2 19.9 4.1 13.8 3.5 5.6A1.5 1.5 0 0 1 5 4z"/>',
        'celular' => '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/>',
        'flecha-derecha' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron-abajo' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-izquierda' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-derecha' => '<path d="m9 6 6 6-6 6"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'check-circulo' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.3 2.4 2.4 4.6-4.9"/>',
        'escudo' => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'ubicacion' => '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'correo' => '<rect x="3" y="5.5" width="18" height="13" rx="1.5"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
        'lapiz' => '<path d="m15.5 4.5 4 4L8 20H4v-4z"/><path d="m13.5 6.5 4 4"/>',
        'basura' => '<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13M10 11v5.5M14 11v5.5"/>',
        'ojo' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'info' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.8v.4"/>',
        'alerta' => '<path d="M12 4 2.8 19.5h18.4z"/><path d="M12 10v4.5M12 17v.4"/>',
        'destello' => '<path d="m12 3.5 1.8 5 5 1.8-5 1.8-1.8 5-1.8-5-5-1.8 5-1.8z"/>',
        'encendido' => '<path d="M12 4v7"/><path d="M7 6.8a7 7 0 1 0 10 0"/>',
        'buscar' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'robot' => '<rect x="5" y="8" width="14" height="11" rx="3"/><path d="M12 4v4M9.5 13v1M14.5 13v1M2.5 13v2M21.5 13v2"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $trazos[$nombre] ?? $trazos['info'] !!}</svg>
