@props(['scheduledConference', 'header' => 'h2'])
<div class="scheduled-conference-summary flex flex-col sm:flex-row gap-4">
    @if($scheduledConference->hasThumbnail())
    <div class="cover max-w-40">
        <img src="{{ $scheduledConference->getThumbnailUrl() }}" alt="{{ $scheduledConference->title }}">
    </div>
    @endif
    <div class="information flex-1 space-y-2">
        @if($scheduledConference->date_start || $scheduledConference->date_end)
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <x-heroicon-c-calendar-days class="h-5 w-5" />
                <div>
                    @if($scheduledConference->date_start)
                        @if($scheduledConference->date_end && $scheduledConference->date_start->format(Setting::get('format_date')) !== $scheduledConference->date_end->format(Setting::get('format_date')))
                            <span class="font-semibold">{{ $scheduledConference->date_start->format(Setting::get('format_date')) }} - {{ $scheduledConference->date_end->format(Setting::get('format_date')) }}</span>
                        @else
                            <span class="font-semibold">{{ $scheduledConference->date_start->format(Setting::get('format_date')) }}</span>
                        @endif
                    @endif
                </div>
            </div>
        @endif

        <{{ $header }} class="">
            <a href="{{ $scheduledConference->getHomeUrl() }}"
                class="scheduled-conference-name link link-primary link-hover font-bold">{{ $scheduledConference->title }}</a>
        </{{ $header }}>

        @if ($scheduledConference->getMeta('summary'))
            <div class="scheduled-conference-summary user-content">
                {!! $scheduledConference->getMeta('summary') !!}
            </div>
        @endif
    </div>
</div>
