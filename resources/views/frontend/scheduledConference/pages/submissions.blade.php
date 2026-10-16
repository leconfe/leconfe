<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative space-y-6">
        <div class="flex space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ __('general.submissions') }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>

        <div class="submissions-notice rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-200">
            @auth
                <ul class="space-y-1">
                    @if ($isOpen)
                        <li>
                            <a href="{{ route('filament.scheduledConference.resources.submissions.create') }}" class="link link-primary">
                                {{ __('general.make_new_submission') }}
                            </a>
                        </li>
                    @else
                        <li>{{ __('general.not_accepting_submissions') }}</li>
                    @endif
                    <li>
                        <a href="{{ route('filament.scheduledConference.resources.submissions.index') }}" class="link link-primary">
                            {{ __('general.view_your_submissions') }}
                        </a>
                    </li>
                </ul>
            @else
                @if ($isOpen)
                    <p>{{ __('general.submissions_guest_notice') }}</p>
                    <p class="mt-2 space-x-3">
                        <a href="{{ route('livewirePageGroup.scheduledConference.pages.login') }}" class="link link-primary">{{ __('general.login') }}</a>
                        <a href="{{ route('livewirePageGroup.scheduledConference.pages.register') }}" class="link link-primary">{{ __('general.register') }}</a>
                    </p>
                @else
                    <p>{{ __('general.not_accepting_submissions') }}</p>
                @endif
            @endauth
        </div>

        @if ($authorGuidelines)
            <section class="author-guidelines space-y-2">
                <h2 class="text-lg font-semibold">{{ __('general.author_guidelines') }}</h2>
                <div class="user-content">
                    {{ new Illuminate\Support\HtmlString($authorGuidelines) }}
                </div>
            </section>
        @endif

        @if ($submissionChecklist)
            <section class="submission-checklist space-y-2">
                <h2 class="text-lg font-semibold">{{ __('general.submission_checklist') }}</h2>
                <div class="user-content">
                    {{ new Illuminate\Support\HtmlString($submissionChecklist) }}
                </div>
            </section>
        @endif

        @if ($tracks->isNotEmpty())
            <section class="track-policies space-y-4">
                <h2 class="text-lg font-semibold">{{ __('general.track_policies') }}</h2>
                @foreach ($tracks as $track)
                    <div class="track-policy space-y-1">
                        <h3 class="font-semibold">{{ $track->translated('title') }}</h3>
                        <div class="user-content">
                            {{ new Illuminate\Support\HtmlString($track->translated('policy')) }}
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($privacyStatement)
            <section class="privacy-statement space-y-2">
                <h2 class="text-lg font-semibold">{{ __('general.privacy_statement') }}</h2>
                <div class="user-content">
                    {{ new Illuminate\Support\HtmlString($privacyStatement) }}
                </div>
            </section>
        @endif
    </div>
</x-website::layouts.main>
