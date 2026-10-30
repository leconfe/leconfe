<span class="inline-flex items-center gap-1">
    {{ __('general.translations') }}
    <span
        class="rounded-md bg-gray-100 px-1.5 text-xs font-medium tabular-nums text-gray-600 dark:bg-white/10 dark:text-gray-300"
        x-text="filled + '/' + {{ $total }}"
    >{{ $filledCount }}/{{ $total }}</span>
    <span class="sr-only" x-text="open ? @js(__('general.hide_translations')) : @js(__('general.show_translations'))"></span>
</span>
