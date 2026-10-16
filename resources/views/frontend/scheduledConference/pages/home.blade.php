<x-website::layouts.main>
    <div class="space-y-8">
        @if ($currentScheduledConference->hasMedia('cover')||$currentScheduledConference->translated('about')||$currentScheduledConference->getMeta('additional_content'))
            <section id="highlight" class="space-y-4">
                <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 gap-4">
                    <div class="flex flex-col gap-4 flex-1">
                        @if ($currentScheduledConference->hasMedia('cover'))
                            <div class="cf-cover">
                                <img class="h-full"
                                    src="{{ $currentScheduledConference->getFirstMedia('cover')->getAvailableUrl(['thumb', 'thumb-xl']) }}"
                                    alt="{{ $currentScheduledConference->translated('title') }}" />
                            </div>
                        @endif
                        @if ($currentScheduledConference->translated('about'))
                            <div class="user-content">
                                {{ new Illuminate\Support\HtmlString($currentScheduledConference->translated('about')) }}
                            </div>
                        @endif
                        @if ($currentScheduledConference->getMeta('additional_content'))
                            <div class="user-content">
                                {{ new Illuminate\Support\HtmlString($currentScheduledConference->getMeta('additional_content')) }}
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif
        @if ($currentScheduledConference?->speakers->isNotEmpty())
            <section id="speakers" class="flex flex-col gap-y-0">
                <x-website::heading-title title="Speakers" class="mb-5"/>
                <div class="cf-speakers space-y-6">
                    @foreach ($currentScheduledConference->speakerRoles as $role)
                        @if ($role->speakers->isNotEmpty())
                            <div class="space-y-4">
                                <h3 class="text-lg">{{ $role->translated('name') }}</h3>
                                <div class="cf-speaker-list grid gap-2 sm:grid-cols-2">
                                    @foreach ($role->speakers as $speaker)
                                        <div class="cf-speaker flex items-center h-full gap-2">
                                            <img class="cf-speaker-img object-cover w-24 h-24 rounded-full aspect-square"
                                                src="{{ $speaker->getFilamentAvatarUrl() }}"
                                                alt="{{ $speaker->fullName }}" />
                                            <div class="cf-speaker-information space-y-1">
                                                <div class="cf-speaker-name text-gray-900">
                                                    {{ $speaker->fullName }}
                                                </div>
                                                @if ($speaker->getMeta('affiliation'))
                                                    <div class="cf-speaker-affiliation text-xs text-gray-700">
                                                        {{ $speaker->getMeta('affiliation') }}</div>
                                                @endif
                                                @include('frontend.scheduledConference.components.contributor-profile-links', ['person' => $speaker])
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if($sponsorLevels->isNotEmpty() || $sponsorsWithoutLevel->isNotEmpty())
            <section class="sponsors">
                <x-website::heading-title title="Sponsors" class="mb-5"/>
                <div class="conference-sponsor-levels space-y-6">
                    @if($sponsorsWithoutLevel->isNotEmpty())
                        <div class="conference-sponsor-level">
                            <div class="conference-sponsors flex flex-wrap items-center gap-4">
                                @foreach($sponsorsWithoutLevel as $sponsor)
                                    @if(!$sponsor->getFirstMedia('logo'))
                                        @continue
                                    @endif
                                    <x-scheduledConference::conference-sponsor :sponsor="$sponsor" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @foreach ($sponsorLevels as $sponsorLevel)
                        <div class="conference-sponsor-level">
                            <h3 class="text-lg mb-4">{{ $sponsorLevel->translated('name') }}</h3>
                            <div class="conference-sponsors flex flex-wrap items-center gap-4">
                                @foreach($sponsorLevel->stakeholders as $sponsor)
                                    @if(!$sponsor->getFirstMedia('logo'))
                                        @continue
                                    @endif
                                    <x-scheduledConference::conference-sponsor :sponsor="$sponsor" />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @if($partners->isNotEmpty())
            <section class="partners">
                <x-website::heading-title title="Partners" class="mb-5"/>
                <div class="conference-partners flex flex-wrap items-center gap-4">
                    @foreach($partners as $partner)
                        @if(!$partner->getFirstMedia('logo'))
                            @continue
                        @endif
                        <x-scheduledConference::conference-partner :partner="$partner" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-website::layouts.main>
