<x-website::layouts.main>
    <div class="space-y-5">
        @if ($site->getMeta('about'))
            <div class="description user-content">
                {{ new Illuminate\Support\HtmlString($site->getMeta('about')) }}
            </div>
        @endif

        @if($featuredScheduledConferences->isNotEmpty())
            <div class="featured-scheduled-conference">
                <x-website::heading-title title="{{ __('general.featured_scheduled_conference') }}" class="grow"/>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 py-4">
                    @foreach ($featuredScheduledConferences as $scheduledConference)
                        <x-website::scheduled-conference-summary :scheduledConference="$scheduledConference" />
                    @endforeach
                </div>
            </div>
        @endif

        @if($scheduledConferences->isNotEmpty())
            <div class="featured-scheduled-conference">
                <x-website::heading-title title="{{ __('general.conferences') }}" class="grow"/>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 py-4">
                    @foreach ($scheduledConferences as $scheduledConference)
                        <x-website::scheduled-conference-summary :scheduledConference="$scheduledConference" />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-website::layouts.main>
