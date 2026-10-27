@extends('layouts.paciente')

@section('titulo', 'Hola, ' . $paciente->nombres)
@section('subtitulo', 'Desde aquí puedes gestionar tus citas y ver tu historia clínica.')

@section('acciones')
    <x-ui.boton :href="route('paciente.citas.create')"><x-icono nombre="mas" class="size-4" /> Agendar cita</x-ui.boton>
@endsection

@section('contenido')
    @if ($proxima)
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-[#7a3aa8] p-7 text-white shadow-lg shadow-primary/20" aria-label="Próxima cita">
            <div class="absolute -right-10 -top-10 size-48 rounded-full bg-white/5"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-[#ffb36b]">Tu próxima cita</p>
                    <p class="mt-2 text-2xl font-bold sm:text-3xl">
                        {{ ucfirst(\Carbon\Carbon::parse($proxima->fecha)->translatedFormat('l d \d\e F')) }} · {{ date('g:i A', strtotime($proxima->hora_inicio)) }}
                    </p>
                    <p class="mt-2 text-white/90">Dr(a). {{ $proxima->medico->nombres }} {{ $proxima->medico->apellidos }} · {{ $proxima->medico->especialidad->nombre ?? '' }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-white/90">
                        <x-icono nombre="ubicacion" class="size-4" /> {{ $consultorio ? "Consultorio {$consultorio}" : 'Consultorio por confirmar' }}
                        <x-ui.insignia tono="sobre-oscuro">{{ $proxima->estado_texto }}</x-ui.insignia>
                    </p>
                    <x-ui.boton :href="route('paciente.citas.edit', $proxima)" variante="claro" tamano="sm" class="mt-5">Reprogramar</x-ui.boton>
                </div>
                <div class="rounded-2xl bg-white/15 px-7 py-4 text-center ring-1 ring-white/20">
                    @if ($diasFaltan === 0)
                        <span class="block text-3xl font-extrabold">Hoy</span>
                    @else
                        <span class="block text-4xl font-extrabold leading-none">{{ $diasFaltan }}</span>
                        <span class="text-sm">{{ $diasFaltan === 1 ? 'día' : 'días' }}</span>
                    @endif
                </div>
            </div>
        </section>
    @else
        <x-ui.tarjeta class="text-center">
            <p class="text-on-surface">No tienes citas próximas.</p>
            <x-ui.boton :href="route('paciente.citas.create')" class="mt-4">Agendar una cita</x-ui.boton>
        </x-ui.tarjeta>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.metrica titulo="Citas próximas" :valor="$resumen['proximas']" tono="info">
            <a href="{{ route('paciente.citas.index') }}" class="underline-offset-2 hover:underline">Ver mis citas</a>
        </x-ui.metrica>
        <x-ui.metrica titulo="Atenciones" :valor="$resumen['atendidas']" tono="exito">
            <a href="{{ route('paciente.historia') }}" class="underline-offset-2 hover:underline">Ver historia clínica</a>
        </x-ui.metrica>
        <x-ui.metrica titulo="Inasistencias" :valor="$resumen['inasistencias']" detalle="Si no puedes asistir, cancela con anticipación" tono="aviso" />
    </div>

    <h2 class="mb-3 mt-8 text-base font-semibold text-on-surface-strong">Últimas atenciones</h2>
    <x-ui.tabla>
        <thead><tr><th>Fecha</th><th>Médico</th><th>Diagnóstico</th></tr></thead>
        <tbody>
            @forelse ($ultimasAtenciones as $historia)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($historia->cita->fecha)->format('d/m/Y') }}</td>
                    <td>Dr(a). {{ $historia->cita->medico->apellidos ?? '—' }}<br><span class="text-xs">{{ $historia->cita->medico->especialidad->nombre ?? '' }}</span></td>
                    <td class="text-on-surface-strong">{{ \Illuminate\Support\Str::limit($historia->diagnostico, 90) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-10 text-center">Aún no tienes atenciones registradas.</td></tr>
            @endforelse
        </tbody>
    </x-ui.tabla>
@endsection
