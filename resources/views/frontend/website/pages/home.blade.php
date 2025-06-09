<x-website::layouts.main>
    <div class="space-y-5">
        @if ($site->getMeta('about'))
            <div class="description user-content">
                {{ new Illuminate\Support\HtmlString($site->getMeta('about')) }}
            </div>
        @endif
        @foreach ($homepage->getBlocks() as $block)
            {!! $block->render() !!}
        @endforeach
    </div>
</x-website::layouts.main>
