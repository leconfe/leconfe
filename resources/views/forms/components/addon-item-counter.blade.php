@php
    $isDisabled = $isDisabled();
    $minValue = $getMinValue();
    $maxValue = $getMaxValue();
    $step = $getStep();
    $label = $getLabel();
    $helperText = $getHelperText();
@endphp

<div {{ $attributes->class(['fi-fo-addon-item-counter']) }}>
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
        <div class="flex-1 min-w-0">
            @if($label)
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ $label }}
                </label>
            @endif
            
            @if($helperText)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ $helperText }}
                </p>
            @endif
        </div>
        
        <div class="flex items-center gap-1 self-start sm:self-center">
            <button
                type="button"
                x-data=""
                @click="
                    const container = $el.closest('.fi-fo-addon-item-counter');
                    const input = container.querySelector('input[type=\'number\']');
                    const min = parseInt(input.min) || 0;
                    let value = parseInt(input.value) || 0;
                    
                    if (value > min) {
                        input.value = value - 1;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                "
                class="flex items-center justify-center w-9 h-9 sm:w-8 sm:h-8 rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                @if($isDisabled) disabled @endif
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                </svg>
            </button>
            
            <input
                type="number"
                readonly
                {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                {{ $attributes->class([
                    'w-14 sm:w-16 text-center border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:border-primary-500 focus:ring-primary-500 sm:text-sm cursor-not-allowed bg-gray-50 dark:bg-gray-800',
                ])->merge([
                    'min' => $minValue,
                    'max' => $maxValue,
                    'step' => $step,
                ]) }}
                @if($isDisabled) disabled @endif
            />
            
            <button
                type="button"
                x-data=""
                @click="
                    const container = $el.closest('.fi-fo-addon-item-counter');
                    const input = container.querySelector('input[type=\'number\']');
                    const max = parseInt(input.max) || 999;
                    let value = parseInt(input.value) || 0;
                    
                    if (value < max) {
                        input.value = value + 1;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                "
                class="flex items-center justify-center w-9 h-9 sm:w-8 sm:h-8 rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                @if($isDisabled) disabled @endif
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
        </div>
    </div>
</div>