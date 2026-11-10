@props(['field'])

<x-filament-forms::field-wrapper :field="$field">
    <p id="{{ $field->getId() }}-description" class="text-sm leading-6 text-gray-600 dark:text-gray-400">
        {{ __('general.publication_date_helper') }}
    </p>

    <div class="mt-2 max-w-xs">
        {{ $slot }}
    </div>
</x-filament-forms::field-wrapper>
