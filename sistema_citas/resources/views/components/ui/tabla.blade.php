{{-- Tabla de Penguin UI: cabecera gris, filas separadas y scroll horizontal en pantallas chicas. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-radius bg-surface shadow-sm ring-1 ring-outline/60']) }}>
    <table class="w-full text-left text-sm text-on-surface
        [&_thead]:border-b [&_thead]:border-outline [&_thead]:bg-surface-alt
        [&_th]:whitespace-nowrap [&_th]:px-4 [&_th]:py-3 [&_th]:text-xs [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-on-surface-strong
        [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle
        [&_tbody_tr]:border-b [&_tbody_tr]:border-outline/60 [&_tbody_tr:last-child]:border-0 [&_tbody_tr:hover]:bg-surface-alt/60">
        {{ $slot }}
    </table>
</div>
