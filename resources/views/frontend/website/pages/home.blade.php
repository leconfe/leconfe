<x-website::layouts.main>
    <div class="space-y-5">
        @if ($site->getMeta('about'))
            <div class="description user-content">
                {{ new Illuminate\Support\HtmlString($site->getMeta('about')) }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 items-center justify-evenly">
            <div class="w-full sm:w-1/3">
                <select wire:model.live="topic"
                    class="w-full bg-transparent outline-none p-2 border rounded-md border-primary">
                    <option value="">{{ __('general.all_topics') }}</option>
                    @foreach($topics as $id => $name)
                        <option value="{{ $name }}" @if(request('topic') === $name) selected @endif>{{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-1/3">
                <select wire:model.live="faculty"
                    class="w-full bg-transparent outline-none p-2 border rounded-md border-primary">
                    <option value="">{{ __('general.all_faculties') ?? __('general.faculty') }}</option>
                    @foreach($faculties as $faculty)
                        <option value="{{ $faculty }}" @if(request('faculty') === $faculty) selected @endif>
                            {{ $faculty }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-1/3">
                <select wire:model.live="conference"
                    class="w-full bg-transparent outline-none p-2 border rounded-md border-primary">
                    <option value="">{{ __('general.all_conferences') ?? __('general.conference') }}</option>
                    @foreach($conferences as $id => $name)
                        <option value="{{ $name }}" @if(request('conference') === $name) selected @endif>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- <button wire:click="resetFilters" class="btn btn-primary w-fit">
                {{ __('general.reset') }}
            </button> --}}
        </div>

        @if($featuredScheduledConferences->isNotEmpty())
            <div class="featured-scheduled-conference">
                <x-website::heading-title title="{{ __('general.featured_scheduled_conference') }}" class="grow" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 py-4">
                    @foreach ($featuredScheduledConferences as $scheduledConference)
                        <x-website::scheduled-conference-summary :scheduledConference="$scheduledConference" />
                    @endforeach
                </div>
            </div>
        @endif

        @if($scheduledConferences->isNotEmpty())
            <div class="featured-scheduled-conference">
                <x-website::heading-title title="{{ __('general.conferences') }}" class="grow" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 py-4">
                    @foreach ($scheduledConferences as $scheduledConference)
                        <x-website::scheduled-conference-summary :scheduledConference="$scheduledConference" />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-website::layouts.main>
