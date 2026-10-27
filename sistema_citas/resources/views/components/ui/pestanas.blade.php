{{-- Pestañas de Penguin UI hechas con enlaces. Uso: <x-ui.pestanas :pestanas="[['url' => ..., 'texto' => ..., 'activa' => true]]" /> --}}
@props(['pestanas' => []])

<nav {{ $attributes->merge(['class' => 'mb-4 flex gap-2 overflow-x-auto border-b border-outline']) }} aria-label="Pestañas">
    @foreach ($pestanas as $pestana)
        <a href="{{ $pestana['url'] }}" @if ($pestana['activa']) aria-current="page" @endif
           @class([
               'whitespace-nowrap px-4 py-2 text-sm transition',
               'border-b-2 border-primary font-bold text-primary' => $pestana['activa'],
               'font-medium text-on-surface hover:border-b-2 hover:border-outline-strong hover:text-on-surface-strong' => !$pestana['activa'],
           ])>{{ $pestana['texto'] }}</a>
    @endforeach
</nav>
