<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-m-clock" icon-color="info">
        <x-slot name="heading">{{ __('translation.seriesWidget.timelineWidgetHeading') }}</x-slot>
        @if ($timeline->isEmpty())
            <h2 class="text-xl text-center text-gray-900 dark:text-white">{{ __('translation.seriesWidget.timelineWidgetH2') }}</h2>
        @else
            <div class="timelines max-h-64 overflow-y-scroll divide-y">
                @foreach ($timeline as $timelines)
                    <div class="timeline py-2 first:pt-0 last:pb-0">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $timelines->date->format(Setting::get('format_date')) }}</h3>
                            <time
                                class="text-sm font-normal leading-none text-gray-400 dark:text-gray-500"> {{ $timelines->title }}</time>
                        </div>
                        <p class="text-sm font-normal text-gray-400 dark:text-gray-500">{{ $timelines->subtitle }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
