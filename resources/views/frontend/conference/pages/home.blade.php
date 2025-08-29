<x-website::layouts.main>
    @if ($conference->hasMedia('cover'))
        <div class="conference-cover">
            <img class="h-full" src="{{ $conference->getFirstMedia('cover')->getAvailableUrl(['thumb', 'thumb-xl']) }}"
                alt="{{ $conference->title }}" />
        </div>
    @endif
    @if ($conference->getLocalizedMeta('about'))
        <div class="conference-about user-content mb-4">
            {!! $conference->getLocalizedMeta('about') !!}
        </div>
    @endif
    @if($nextScheduledConference || $pastScheduledConferences->isNotEmpty())
    <div class="scheduled-conferences space-y-6">
        @if($nextScheduledConference)
            <div class="next-scheduled-conference">
                <x-website::heading-title :title="__('general.current_conference')" class="mb-5" />
                <div class="next-scheduled-conference sm:flex gap-4">
                    @if ($nextScheduledConference->hasThumbnail())
                        <div class="next-scheduled-conference-cover max-w-40">
                            <img src="{{ $nextScheduledConference->getThumbnailUrl() }}" alt="{{ $nextScheduledConference->title }}">
                        </div>
                    @endif
                    <div class="information flex-1 space-y-3">
                        <div>
                            <h3 class="next-scheduled-conference-title">
                                <a href="{{ $nextScheduledConference->getHomeUrl() }}"
                                    class="link link-primary link-hover font-medium">{{ $nextScheduledConference->title }}</a>
                            </h3>
                            <div class="next-scheduled-conference-date text-sm text-gray-700">
                                @if($nextScheduledConference->date_start)
                                    {{ $nextScheduledConference->date_start->format(Setting::get('format_date')) }}
                                @endif
                                @if($nextScheduledConference->date_end)
                                    - {{ $nextScheduledConference->date_end->format(Setting::get('format_date')) }}
                                @endif
                            </div>
                        </div>

                        @if ($nextScheduledConference->getLocalizedMeta('summary'))
                            <div class="scheduled-conference-summary user-content">
                                {!! $nextScheduledConference->getLocalizedMeta('summary') !!}
                            </div>
                        @endif
                        <div class="next-scheduled-conference-link">
                            <a href="{{ $nextScheduledConference->getHomeUrl() }}" class="link link-primary text-sm">{{ __('general.view_current_event') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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

                                @if ($scheduledConference->getLocalizedMeta('summary'))
                                    <div class="scheduled-conference-summary user-content">
                                        {!! $scheduledConference->getLocalizedMeta('summary') !!}
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

                                @if ($scheduledConference->getLocalizedMeta('summary'))
                                    <div class="scheduled-conference-summary user-content">
                                        {!! $scheduledConference->getLocalizedMeta('summary') !!}
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
