<x-website::layouts.main>
    <div class="space-y-5">
        @foreach ($homepage->getBlocks() as $block)
            {!! $block->render() !!}
        @endforeach
    </div>
</x-website::layouts.main>
