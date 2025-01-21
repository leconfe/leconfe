<div class="flex min-h-screen flex-col items-center">
    <div class="flex w-full flex-grow items-center justify-center mt-4">
        <main @class(['max-w-xl w-full'])>
            <div class="flex items-center justify-center">
                <x-website::logo :headerLogo="$headerLogo" />
            </div>

            <div class="my-4 bg-white px-6 py-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:rounded-xl sm:px-12 space-y-4">
                <div class="font-bold text-xl">
                    {{ $this->getTitle()}}
                </div>

                <x-filament-panels::form
                    id="form" 
                    wire:submit="submit"
                    class="space-y-2"
                    >
                    {{ $this->form }}

                    <div class="flex">
                        {{ $this->submitAction }}
                    </div>
                </x-filament-panels::form>
            </div>
        </main>
    </div>
    <x-footer-platform-panel />
</div>
