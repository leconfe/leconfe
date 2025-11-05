<x-website::layouts.main>
    @if ($conference->hasMedia('cover'))
        <div class="conference-cover">
            <img class="h-full" src="{{ $conference->getFirstMedia('cover')->getAvailableUrl(['thumb', 'thumb-xl']) }}"
                alt="{{ $conference->title }}" />
        </div>
    @endif
    @if ($conference->getMeta('about'))
        <div class="conference-about user-content mb-4">
            {!! $conference->getMeta('about') !!}
        </div>
    @endif
    @if($upcomingScheduledConferences->isNotEmpty() || $pastScheduledConferences->isNotEmpty())
    <div class="scheduled-conferences space-y-6">
        @if($upcomingScheduledConferences->isNotEmpty())
            <div class="scheduled-conferences">
                <x-website::heading-title :title="__('general.upcoming_conference')" class="mb-5" />
                <div class="space-y-6">
                    @foreach ($upcomingScheduledConferences as $scheduledConference)
                        <div class="scheduled-conference sm:flex gap-4">
                            @if ($scheduledConference->hasThumbnail())
                                <div class="scheduled-conference-cover max-w-40">
                                    <img src="{{ $scheduledConference->getThumbnailUrl() }}" alt="{{ $scheduledConference->title }}">
                                </div>
                            @endif
                            <div class="information flex-1 space-y-3">
                                <div>
                                    <h3 class="scheduled-conference-title">
                                        <a href="{{ $scheduledConference->getHomeUrl() }}"
                                            class="link link-primary link-hover font-medium">{{ $scheduledConference->title }}</a>
                                    </h3>
                                    <div class="scheduled-conference-date text-sm text-gray-700">
                                        @if($scheduledConference->date_start)
                                            {{ $scheduledConference->date_start->format(Setting::get('format_date')) }}
                                        @endif
                                        @if($scheduledConference->date_end)
                                            - {{ $scheduledConference->date_end->format(Setting::get('format_date')) }}
                                        @endif
                                    </div>
                                </div>

                                @if ($scheduledConference->getMeta('summary'))
                                    <div class="scheduled-conference-summary user-content">
                                        {!! $scheduledConference->getMeta('summary') !!}
                                    </div>
                                @endif
                                <div class="scheduled-conference-link">
                                    <a href="{{ $scheduledConference->getHomeUrl() }}" class="link link-primary text-sm">{{ __('general.view_event') }}</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($pastScheduledConferences->isNotEmpty())
            <div class="scheduled-conferences">
                <x-website::heading-title :title="__('general.past_conference')" class="mb-5" />
                <div class="space-y-6">
                     @foreach ($pastScheduledConferences as $scheduledConference)
                        <div class="scheduled-conference sm:flex gap-4">
                            @if ($scheduledConference->hasThumbnail())
                                <div class="scheduled-conference-cover max-w-40">
                                    <img src="{{ $scheduledConference->getThumbnailUrl() }}" alt="{{ $scheduledConference->title }}">
                                </div>
                            @endif
                            <div class="information flex-1 space-y-3">
                                <div>
                                    <h3 class="scheduled-conference-title">
                                        <a href="{{ $scheduledConference->getHomeUrl() }}"
                                            class="link link-primary link-hover font-medium">{{ $scheduledConference->title }}</a>
                                    </h3>
                                    <div class="scheduled-conference-date text-sm text-gray-700">
                                        @if($scheduledConference->date_start)
                                            {{ $scheduledConference->date_start->format(Setting::get('format_date')) }}
                                        @endif
                                        @if($scheduledConference->date_end)
                                            - {{ $scheduledConference->date_end->format(Setting::get('format_date')) }}
                                        @endif
                                    </div>
                                </div>

                                @if ($scheduledConference->getMeta('summary'))
                                    <div class="scheduled-conference-summary user-content">
                                        {!! $scheduledConference->getMeta('summary') !!}
                                    </div>
                                @endif
                                <div class="scheduled-conference-link">
                                    <a href="{{ $scheduledConference->getHomeUrl() }}" class="link link-primary text-sm">{{ __('general.view_event') }}</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
    @endif
</x-website::layouts.main>
