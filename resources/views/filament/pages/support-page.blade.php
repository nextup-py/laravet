<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">¿Algo no funciona o tienes una duda?</x-slot>

        <div class="prose max-w-none dark:prose-invert">
            <p>Escríbenos a <strong>{{ $this->getSupportEmail() }}</strong> y te ayudamos.</p>
            <p>Para resolverlo más rápido, cuéntanos:</p>
            <ul>
                <li>En qué pantalla estabas.</li>
                <li>Qué estabas haciendo.</li>
                <li>Qué esperabas que pasara y qué pasó en cambio.</li>
            </ul>
        </div>

        <div class="mt-6">
            <x-filament::button tag="a" :href="$this->getMailtoUrl()" icon="heroicon-o-envelope">
                Escribir a soporte
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
