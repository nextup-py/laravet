<x-filament-panels::page>
    @php($current = $this->getCurrentTopic())

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        <nav class="flex flex-col gap-1" aria-label="Temas de la guía">
            @foreach ($this->getTopics() as $topic)
                <button
                    type="button"
                    wire:click="$set('topic', '{{ $topic['slug'] }}')"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                        'bg-primary-50 text-primary-600 dark:bg-white/5 dark:text-primary-400' => $current && $current['slug'] === $topic['slug'],
                        'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5' => ! $current || $current['slug'] !== $topic['slug'],
                    ])
                >
                    <x-filament::icon :icon="$topic['icon']" class="h-5 w-5 shrink-0" />
                    <span>{{ $topic['title'] }}</span>
                </button>
            @endforeach
        </nav>

        <x-filament::section>
            @if ($current)
                <article class="prose max-w-none dark:prose-invert">
                    {!! $this->getCurrentHtml() !!}
                </article>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Todavía no hay temas de la guía disponibles para tu rol.
                </p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
