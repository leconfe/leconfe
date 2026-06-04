<div class="fi-simple-page">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <a
        href="{{ $this->getAuthLogoHomeUrl() }}"
        class="mb-6 inline-flex items-center gap-x-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-white/5"
    >
        <x-heroicon-m-arrow-left class="h-4 w-4" />
        Back to home
    </a>

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

        @if (! $success)
            @error('throttle')
                <div class="text-sm text-danger-600 dark:text-danger-400">
                    {{ $message }}
                </div>
            @enderror

            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('general.please_enter_email_to_reset_password') }}
            </p>

            <x-filament-panels::form wire:submit="submit">
                {{ $this->form }}

                <x-filament-panels::form.actions :actions="$this->getFormActions()" :fullWidth="true" />
            </x-filament-panels::form>
        @else
            <div class="space-y-4">
                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ __('general.reset_password_mail_sent') }}
                </p>

                <a href="{{ app()->getLoginUrl() }}" class="fi-simple-link">
                    {{ __('general.login') }}
                </a>
            </div>
        @endif
    </section>

    <x-filament-actions::modals />

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
