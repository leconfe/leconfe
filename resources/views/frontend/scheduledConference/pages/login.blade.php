<div class="fi-simple-page">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <section class="grid auto-cols-fr gap-y-6">
        <header class="fi-simple-header flex flex-col items-center">
            <a href="{{ $this->getAuthLogoHomeUrl() }}" class="mb-4">
                <img
                    src="{{ $this->getAuthLogoUrl() }}"
                    alt="{{ $this->getAuthLogoAltText() }}"
                    class="fi-logo max-h-20 w-auto object-contain"
                />
            </a>

            <h1 class="fi-simple-header-heading text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                {{ $this->getHeading() }}
            </h1>
        </header>

        <x-filament-panels::form wire:submit="login">
            {{ $this->form }}

            <label class="label-text">
                <x-website::link :href="$resetPasswordUrl"
                    class="link link-primary">{{ __('general.forgot_password_question') }}</x-website::link>
            </label>
            <x-filament-panels::form.actions :actions="$this->getFormActions()" :fullWidth="true" />
        </x-filament-panels::form>
    </section>

    <x-footer-platform-panel />

    <x-filament-actions::modals />

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
