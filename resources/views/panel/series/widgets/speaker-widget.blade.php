<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-m-user-group" icon-color="info">
        <x-slot name="heading">{{ __('translation.seriesWidget.speakerWidgetHeading') }}</x-slot>
    
        @if ($speakers->isEmpty())
            <h2 class="text-xl text-center text-gray-900 dark:text-white">{{ __('translation.seriesWidget.speakerWidgetH2') }}</h2>
        @else
            <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($speakers as $speaker)
                    <div class="py-2 mx-auto image">
                        <img src="{{ $speaker->getFirstMedia('profile') ? $speaker->getFirstMedia('profile')->getFullUrl('avatar') : 'https://ui-avatars.com/api/?name=' . urlencode($speaker->fullname) . '&color=FFFFFF&background=09090b' }}" alt="" class="mx-auto rounded-full w-11 h-11">
                        <div class="text-center">
                            {{-- Speaker name --}}
                            <h2 class="text-sm text-gray-900 dark:text-white">{{ $speaker->fullName }}</h2>
                    
                            {{-- Expertise from the speaker --}}
                            <p class="text-xs text-gray-500">
                                @if ($speaker->getMeta('expertise'))
                                    @foreach ($speaker->getMeta('expertise') as $expertise)
                                        @unless ($loop->first)
                                            ,
                                        @endunless
                                        {{ $expertise }}
                                    @endforeach
                                @endif                        
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
    </x-filament::section>
</x-filament-widgets::widget>
