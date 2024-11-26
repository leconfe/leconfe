<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <section class="flex flex-col gap-y-0">
        <x-website::heading-title title="Committees" class="mb-5" tag="h1" />
        <div class="cf-committees space-y-6">
            @foreach ($committeeRoles as $role)
                @if ($role->committees->isNotEmpty())
                    <div class="space-y-4">
                        <h3 class="text-lg">{{ $role->name }}</h3>
                        <div class="cf-speaker-list grid gap-2 sm:grid-cols-2">
                            @foreach ($role->committees as $committee)
                                <div class="cf-committee flex items-center h-full gap-2">
                                    <img class="cf-committee-img object-cover w-16 h-16 rounded-full aspect-square"
                                        src="{{ $committee->getFilamentAvatarUrl() }}" alt="{{ $committee->fullName }}" />
                                    <div class="cf-committee-information space-y-1">
                                        <div class="cf-committee-name text-gray-900">
                                            {{ $committee->fullName }}
                                        </div>
                                        @if ($committee->getMeta('affiliation'))
                                            <div class="cf-committee-affiliation text-xs text-gray-700">
                                                {{ $committee->getMeta('affiliation') }}</div>
                                        @endif
                                        @if ($committee->getMeta('scopus_url') || $committee->getMeta('google_scholar_url') || $committee->getMeta('orcid_url'))
                                            <div class="cf-committee-scholar flex flex-wrap items-center gap-1">
                                                @if ($committee->getMeta('orcid_url'))
                                                    <a href="{{ $committee->getMeta('orcid_url') }}" target="_blank">
                                                        <x-academicon-orcid class="orcid-logo" />
                                                    </a>
                                                @endif
                                                @if ($committee->getMeta('google_scholar_url'))
                                                    <a href="{{ $committee->getMeta('google_scholar_url') }}"
                                                        target="_blank">
                                                        <x-academicon-google-scholar class="google-scholar-logo" />
                                                    </a>
                                                @endif
                                                @if ($committee->getMeta('scopus_url'))
                                                    <a href="{{ $committee->getMeta('scopus_url') }}" target="_blank">
                                                        <x-academicon-scopus class="scopus-logo" />
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
</x-website::layouts.main>
