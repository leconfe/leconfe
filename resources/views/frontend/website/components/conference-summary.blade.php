@props(['conference', 'header' => 'h2'])

<div class="conference-summary sm:flex gap-4">
    <div class="cover max-w-40">
        <img src="{{ $conference->getThumbnailUrl() }}" alt="{{ $conference->getLocalizedMeta('name') }}">
    </div>
    <div class="information flex-1 space-y-2">
        @if ($conference?->activeSerie?->date_start)
            <div class="flex items-center text-sm gap-2 text-gray-600">
                <x-heroicon-c-calendar-days class="h-5 w-5" />
                <span>
                    {{ $conference?->activeSerie?->date_start?->format(Setting::get('format_date')) }}
                </span>
            </div>
        @endif

        <{{ $header }} class="">
            <a href="{{ $conference->getHomeUrl() }}"
                class="conference-name link link-primary link-hover font-bold">{{ $conference->getLocalizedMeta('name') }}</a>
            {{-- <span class="badge badge-sm">{{ $conference->type }}</span> --}}
        </{{ $header }}>

        @if ($conference->getMeta('description'))
            <p class="conference-description line-clamp-4 text-sm">{{ $conference->getMeta('description') }}</p>
        @endif

        <a href="{{ $conference->getHomeUrl() }}" class="btn btn-primary btn-sm">Check Conference</a>
    </div>
</div>
