@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="w-full">
        <div class="flex items-center justify-between w-full sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center h-9 px-3 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-lg">
                    Previous
                </span>
            @else
                <button
                    type="button"
                    wire:click="previousPage"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center h-9 px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"
                >
                    Previous
                </button>
            @endif

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    wire:click="nextPage"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center h-9 px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"
                >
                    Next
                </button>
            @else
                <span class="inline-flex items-center h-9 px-3 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-lg">
                    Next
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:items-center sm:justify-between w-full">
            <div class="text-sm text-gray-600">
                Showing
                <span class="font-medium text-gray-800">{{ $paginator->firstItem() }}</span>
                to
                <span class="font-medium text-gray-800">{{ $paginator->lastItem() }}</span>
                of
                <span class="font-medium text-gray-800">{{ $paginator->total() }}</span>
                results
            </div>

            <div>
                <span class="relative z-0 inline-flex rounded-lg shadow-sm -space-x-px" aria-label="Pagination">
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="@lang('pagination.previous')">
                            <span class="relative inline-flex items-center h-9 px-2 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-l-lg">
                                <span class="sr-only">@lang('pagination.previous')</span>
                                <x-heroicon-m-chevron-left class="h-4 w-4" />
                            </span>
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="previousPage"
                            wire:loading.attr="disabled"
                            class="relative inline-flex items-center h-9 px-2 py-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-l-lg hover:bg-gray-50"
                            aria-label="@lang('pagination.previous')"
                        >
                            <span class="sr-only">@lang('pagination.previous')</span>
                            <x-heroicon-m-chevron-left class="h-4 w-4" />
                        </button>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center h-9 px-4 py-2 text-sm text-gray-500 bg-white border border-gray-200">
                                    {{ $element }}
                                </span>
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center h-9 px-4 py-2 text-sm font-semibold text-white bg-primary-600 border border-primary-600">
                                            {{ $page }}
                                        </span>
                                    </span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="gotoPage({{ $page }})"
                                        wire:loading.attr="disabled"
                                        class="relative inline-flex items-center h-9 px-4 py-2 text-sm text-gray-600 bg-white border border-gray-200 hover:bg-gray-50"
                                        aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    >
                                        {{ $page }}
                                    </button>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <button
                            type="button"
                            wire:click="nextPage"
                            wire:loading.attr="disabled"
                            class="relative inline-flex items-center h-9 px-2 py-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-r-lg hover:bg-gray-50"
                            aria-label="@lang('pagination.next')"
                        >
                            <span class="sr-only">@lang('pagination.next')</span>
                            <x-heroicon-m-chevron-right class="h-4 w-4" />
                        </button>
                    @else
                        <span aria-disabled="true" aria-label="@lang('pagination.next')">
                            <span class="relative inline-flex items-center h-9 px-2 py-2 text-sm text-gray-400 bg-white border border-gray-200 rounded-r-lg">
                                <span class="sr-only">@lang('pagination.next')</span>
                                <x-heroicon-m-chevron-right class="h-4 w-4" />
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
