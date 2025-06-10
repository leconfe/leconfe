<div class="speaker-block p-4 space-y-4">
    @if (!empty($description))
        <div class="prose prose-sm max-w-none">
            {!! $description !!}
        </div>
    @endif
	<div class="speakers grid gap-4 sm:grid-cols-2">
        @foreach ($speakers as $speaker)
            <div class="speaker flex h-full gap-2">
                @if (!empty($speaker['profile_picture']))
                    <img class="speaker-profile-picture object-cover w-16 h-16 rounded-full aspect-square"
                        src="{{ url(array_values($speaker['profile_picture'])[0]) }}" alt="{{ $speaker['name'] }}" />
                @endif
                <div class="speaker-informations">
                    <div class="speaker-name text-gray-900 font-bold">
                        {{ $speaker['name'] }}
                    </div>
                    @if ($affiliation = Arr::get($speaker, 'affiliation'))
                        <div class="speaker-affiliation text-sm text-gray-700 font-medium">
                            {{ $affiliation }}</div>
                    @endif
                    @if ($biography = Arr::get($speaker, 'biography'))
                        <div class="speaker-biography text-sm text-gray-600">{{ $biography }}</div>
                    @endif
                    <div class="speaker-scholars flex flex-wrap items-center gap-1">
                        @if ($orcidUrl = Arr::get($speaker, 'orcid_url'))
                            <a href="{{ $orcidUrl }}" target="_blank">
                                <x-academicon-orcid class="orcid-logo" />
                            </a>
                        @endif
                        @if ($googleScholarUrl = Arr::get($speaker, 'google_scholar_url'))
                            <a href="{{ $googleScholarUrl }}" target="_blank">
                                <x-academicon-google-scholar class="google-scholar-logo" />
                            </a>
                        @endif
                        @if ($scopusUrl = Arr::get($speaker, 'scopus_url'))
                            <a href="{{ $scopusUrl }}" target="_blank">
                                <x-academicon-scopus class="scopus-logo" />
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
