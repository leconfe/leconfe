@use('App\Constants\SubmissionFileCategory')
@use('App\Models\Enums\SubmissionStatus')

<x-website::layouts.main>
    <div id="submission-detail">
        <div class="mb-6">
            <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
        </div>
        @if(!$paper->isPublished())
            <div role="alert" class="gap-2.5 alert bg-yellow-200/20 border-yellow-200/40">
                <x-heroicon-s-eye class="w-5 h-5 my-auto text-amber-600" />
                <div class="text-amber-900">
                    <span class="text-amber-500">Preview. </span> This paper is not published yet. This is only the preview of the paper.
                </div>
            </div>
        @endif
        <x-website::heading-title tag="h1" :title="$paper->getMeta('title')" />
        <div class="mb-4 text-sm text-slate-400">
            <span class="flex items-center ">
                <x-lineawesome-calendar-check-solid class="w-3 h-3 mr-0.5" />
                <span class="citation_publication_date">{{ __('general.paper_date_published', ['date' => $paper->published_at && $paper->isPublished() ? $paper->published_at->format(Setting::get('format_date')) : '-'])  }}</span>
            </span>
        </div>
        @if($paper->getFirstMedia('cover'))
            <div class="mb-4 max-w-[48rem]">
                <img class="w-auto" src="{{ $paper->getFirstMedia('cover')->getAvailableUrl(['thumb']) }}" alt="paper-cover">
            </div>
        @endif
        <div class="submission-detail space-y-8">
            <section class="contributors">
                <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                    {{ __('general.contributors') }}
                </h2>
                <div
                    class="grid grid-cols-2 gap-4 p-4 mt-3 border rounded-md shadow-sm content bg-slate-100 border-slate-200 text-slate-700">
                    @foreach ($paper->authors as $contributor)
                        <div class="col-span-2 sm:col-span-1">
                            <div class="flex items-center">
                                <x-lineawesome-user class="w-5 h-5 mr-1" />
                                <h3 class="citation_author">{{ $contributor->fullName }}</h3>
                            </div>
                            <div class="ml-[25px] text-sm text-slate-500">{{ $contributor->role->name }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
            @if($paper->doi?->doi)
                <section class="doi">
                    <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                        DOI
                    </h2>
                    <div class="content text-slate-800">
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $paper->doi->getUrl() }}" class="flex space-x-1 text-primary text-sm w-max">
                                <x-academicon-doi class="w-4 h-4 my-auto text-yellow-400" />
                                <span>{{ $paper->doi->doi }}</span>
                            </a>
                        </div>
                    </div>
                </section>
            @endif
            @if($paper->getMeta('keywords'))
                <section class="keywords">
                    <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                        {{ __('general.keywords') }}
                    </h2>
                    <div class="content text-slate-800">
                        <div class="flex flex-wrap gap-3">
                            @foreach ($paper->getMeta('keywords') as $keyword)
                                <span
                                    class="flex items-center px-2 py-1 text-xs border rounded-md shadow-sm bg-slate-100 border-slate-200 link-primary">
                                    {{ $keyword }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
            @if($paper->proceeding)
                <section class="proceeding">
                    <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                        {{ __('general.proceeding') }}
                    </h2>
                    <div class="content text-slate-800">
                        <a href="{{ $paper->proceeding->getUrl() }}" class="link link-hover link-primary text-sm ">
                            {{ $paper->proceeding->seriesTitle() }}
                        </a>
                    </div>
                </section>
            @endif
            @if($paper->track)
                <section class="track">
                    <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                        {{ __('general.track') }}
                    </h2>
                    <div class="content text-slate-800">
                        <p class="text-sm">{{ $paper->track->title }}</p>
                    </div>
                </section>
            @endif
            @if($paper->getMeta('license_url') || app()->getCurrentConference()->getMeta('license_terms'))
                <section class="license">
                    <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                        {{ __('general.license') }}
                    </h2>
                    <div class="user-content text-slate-800 text-sm">
                        @if($paper->getMeta('license_url'))
                            @if($ccLicenseBadge)
                                @if($paper->getMeta('copyright_holder'))
                                    <p>{{ __('general.copyright_statement', ['copyrightHolder' => $paper->getMeta('copyright_holder'), 'copyrightYear' => $paper->getMeta('copyright_year')]) }}</p>
                                @endif
                                {!! $ccLicenseBadge !!}
                            @else
                                <a href="{{ $paper->getMeta('license_url') }}" class="copyright">
                                    {{ !$paper->getMeta('copyright_holder') ? __('general.license') :  __('general.copyright_statement', ['copyrightHolder' => $paper->getMeta('copyright_holder'), 'copyrightYear' => $paper->getMeta('copyright_year')]) }}
                                </a>
                            @endif
                        @endif
                        @if(app()->getCurrentConference()->getMeta('license_terms'))
                            {!! app()->getCurrentConference()->getMeta('license_terms') !!}
                        @endif
                    </div>
                </section>
            @endif
            <section class="abstract">
                <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                    {{ __('general.abstract') }}
                </h2>
                <div class="citation_abstract content user-content text-sm">
                    {!! $paper->getMeta('abstract') !!}
                </div>
            </section>
            <section class="references">
                <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                    {{ __('general.references') }}
                </h2>
                <div class="content user-content">
                        @if ($paper->getMeta('references'))
                            @foreach(collect(explode(PHP_EOL, $this->paper->getMeta('references')))->filter()->values() as $reference)
                                <div class="reference">{{ $reference }}</div>
                            @endforeach
                        @else
                            <span class=" text-slate-400">
                                {{ __('general.no_references') }}
                            </span>
                        @endif
                </div>
            </section>
            @if($paper->galleys->isNotEmpty())
                <section class="downloads">
                    <h2 class="pb-1 mb-3 text-base font-medium border-b border-b-slate-200">
                        {{ __('general.downloads') }}
                    </h2>
                    <div class="mt-4 content text-slate-800">
                        <div class="download flex flex-wrap gap-1.5 mt-2">
                            @foreach ($paper->galleys as $galley)
                                <x-scheduledConference::galley-link :galley="$galley"/>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            @livewire(App\Livewire\CitationStyleLanguage::class, ['submission' => $paper])

            @hook('Frontend::Paper::Detail', $paper)
        </div>
        
        @hook('Frontend::Paper::Footer', $paper)
    </div>
</x-website::layouts.main>
