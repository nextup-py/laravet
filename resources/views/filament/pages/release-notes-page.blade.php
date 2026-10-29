<x-filament-panels::page>
    @forelse ($this->getEntries() as $entry)
        <x-filament::section>
            <x-slot name="heading">{{ $entry['title'] }}</x-slot>
            <x-slot name="description">{{ $entry['date']->translatedFormat('d/m/Y') }}</x-slot>

            <article class="prose max-w-none dark:prose-invert">
                {!! $entry['html'] !!}
            </article>
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay novedades.</p>
    @endforelse
</x-filament-panels::page>
