<x-filament-panels::page>
    <style>
        .report-preview td { white-space: nowrap; }
        .report-preview thead th { position: sticky; top: 0; z-index: 1; background: rgb(249 250 251); }
        .report-preview tbody tr:nth-child(even) { background: rgb(0 0 0 / 0.025); }
        .dark .report-preview thead th { background: rgb(31 41 55); }
        .dark .report-preview tbody tr:nth-child(even) { background: rgb(255 255 255 / 0.04); }
    </style>

    <form wire:submit="generate" class="space-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-play">
                Generar reporte
            </x-filament::button>
        </div>
    </form>

    @if ($resultTitle)
        <x-filament::section>
            <x-slot name="heading">{{ $resultTitle }}</x-slot>
            @if ($resultSubtitle)
                <x-slot name="description">{{ $resultSubtitle }}</x-slot>
            @endif

            @if ($total === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay datos para los criterios elegidos.</p>
            @else
                <p class="mb-3 text-sm {{ $total > count($preview) ? 'font-medium text-warning-600 dark:text-warning-400' : 'text-gray-500 dark:text-gray-400' }}">
                    {{ $total > count($preview) ? 'Mostrando '.count($preview).' de '.$total.' filas; la descarga incluye todas.' : $total.' '.($total === 1 ? 'fila' : 'filas').'.' }}
                </p>

                <div class="report-preview overflow-auto rounded-lg ring-1 ring-gray-200 dark:ring-white/10" style="max-height: 32rem;">
                    <table class="w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                        <thead>
                            <tr>
                                @foreach ($headers as $header)
                                    <th class="whitespace-nowrap px-3 py-2 text-start font-semibold text-gray-950 dark:text-white">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach ($preview as $row)
                                <tr>
                                    @foreach ($row as $value)
                                        @php($text = (string) $value)
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300" @if (mb_strlen($text) > 60) title="{{ $text }}" @endif>{{ \Illuminate\Support\Str::limit($text, 60) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
