<x-filament-panels::page.simple>
    <x-filament-panels::form wire:submit="login">
        {{ $this->form }}

        <label class="label-text">
            <x-website::link :href="$resetPasswordUrl"
                class="link link-primary">{{ __('general.forgot_password_question') }}</x-website::link>
        </label>
        <x-filament-panels::form.actions :actions="$this->getFormActions()" :fullWidth="true" />
    </x-filament-panels::form>
</x-filament-panels::page.simple>
