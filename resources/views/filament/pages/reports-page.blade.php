<x-filament-panels::page>
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
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                        <thead>
                            <tr>
                                @foreach ($headers as $header)
                                    <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach ($preview as $row)
                                <tr>
                                    @foreach ($row as $value)
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ $total > count($preview) ? 'Mostrando '.count($preview).' de '.$total.' filas; la descarga incluye todas.' : $total.' '.($total === 1 ? 'fila' : 'filas').'.' }}
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
