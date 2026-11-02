<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">¿Algo no funciona o tienes una duda?</x-slot>

        <div class="prose max-w-none dark:prose-invert">
            <p>La forma más rápida es <strong>reportar el problema desde el sistema</strong>: queda registrado con la pantalla, el navegador y la versión, y puedes adjuntar una captura.</p>
        </div>

        <div class="mt-4">
            <x-filament::button tag="a" :href="$this->getReportUrl()" icon="heroicon-o-flag">
                Reportar un problema
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">También puedes escribirnos</x-slot>

        <div class="prose max-w-none dark:prose-invert">
            <p>Escríbenos a <strong>{{ $this->getSupportEmail() }}</strong> y te ayudamos. Para resolverlo más rápido, cuéntanos:</p>
            <ul>
                <li>En qué pantalla estabas.</li>
                <li>Qué estabas haciendo.</li>
                <li>Qué esperabas que pasara y qué pasó en cambio.</li>
            </ul>
        </div>

        <div class="mt-6 flex flex-wrap gap-3" x-data="{ copied: false }">
            <x-filament::button tag="a" :href="$this->getMailtoUrl()" icon="heroicon-o-envelope" color="gray">
                Escribir a soporte
            </x-filament::button>
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-clipboard-document"
                x-on:click="navigator.clipboard.writeText(@js($this->getSupportEmail())); copied = true; setTimeout(() => copied = false, 2000)"
            >
                <span x-show="! copied">Copiar correo</span>
                <span x-show="copied" x-cloak>¡Copiado!</span>
            </x-filament::button>
        </div>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Si el botón no abre tu programa de correo, copia la dirección y escríbenos desde tu correo habitual.</p>
    </x-filament::section>
</x-filament-panels::page>
