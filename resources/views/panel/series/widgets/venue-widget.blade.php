<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-m-building-office-2" icon-color="info">
        <x-slot name="heading">{{ __('translation.seriesWidget.venueWidgetHeading') }}</x-slot>

        @if ($venues->isEmpty())
            <h2 class="text-xl text-center text-gray-900 dark:text-white">{{ __('translation.seriesWidget.venueWidgetH2') }}</h2>
        @else
            @foreach ($venues as $venue)
                <div class="flex gap-3 p-2">
                    @if ($venue->hasMedia('thumbnail'))
                        <div class="flex border">
                            <img src="{{ $venue->getFirstMedia('thumbnail')->getAvailableUrl(['small', 'thumb', 'thumb-xl']) }}"
                                alt="{{ $venue->name }}">
                        </div>
                    @endif
                    <div class="flex flex-col">
                        <div class="s">
                            <x-filament::link size="sm" color='info' class="font-thin">
                                {{ $venue->name }}
                            </x-filament::link>
                            <p class="text-gray-500 text-xs">{{ $venue->location }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
