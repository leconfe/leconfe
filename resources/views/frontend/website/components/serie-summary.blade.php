@props(['serie', 'header' => 'h2'])
<div class="serie-summary sm:flex gap-4">
    @if($serie->hasThumbnail())
    <div class="cover max-w-40">
        <img src="{{ $serie->getThumbnailUrl() }}" alt="{{ $serie->title }}">
    </div>
    @endif
    <div class="information flex-1 space-y-2">
        @if ($serie->date_start)
            <div class="flex items-center text-sm gap-2 text-gray-600">
                <x-heroicon-c-calendar-days class="h-5 w-5" />
                <span>
                    {{ $serie->date_start?->format(Setting::get('format_date')) }}
                </span>
            </div>
        @endif

        <{{ $header }} class="">
            <a href="{{ $serie->getHomeUrl() }}"
                class="serie-name link link-primary link-hover font-bold">{{ $serie->title }}</a>
        </{{ $header }}>

        @if ($serie->getMeta('description'))
            <p class="serie-description text-sm">{{ $serie->getMeta('description') }}</p>
        @endif

        <a href="{{ $serie->getHomeUrl() }}" class="btn btn-primary btn-sm">{{ __('translation.button.checkConference') }}</a>
    </div>
</div>
