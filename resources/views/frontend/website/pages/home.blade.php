<x-website::layouts.main>
    <div class="space-y-5">
        @if ($site->getMeta('about'))
            <div class="description user-content">
                {{ new Illuminate\Support\HtmlString($site->getMeta('about')) }}
            </div>
        @endif
        <div class="space-y-4 conferences">
            <x-website::heading-title title="{{ __('general.conference_list') }}" class="grow"/>
        </div>
    </div>
</x-website::layouts.main>
