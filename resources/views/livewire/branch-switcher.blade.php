<div>
    @if ($visible)
        <x-filament::dropdown placement="bottom-end">
            <x-slot name="trigger">
                <x-filament::button color="gray" size="sm" icon="heroicon-o-building-storefront" outlined>
                    {{ $current ? $current->name : 'Sucursal' }}
                </x-filament::button>
            </x-slot>

            <x-filament::dropdown.list>
                @foreach ($branches as $branch)
                    <x-filament::dropdown.list.item
                        wire:click="switch({{ $branch->id }})"
                        :icon="$current?->is($branch) ? 'heroicon-m-check' : null"
                    >
                        {{ $branch->code }} · {{ $branch->name }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif
</div>
