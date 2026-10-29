<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                Enviar reporte
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
