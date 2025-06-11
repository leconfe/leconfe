<x-website::layouts.main>
    <div class="flex flex-col gap-8">
        @foreach ($homepage->getBlocks() as $block)
            {!! $block->render() !!}
        @endforeach
    </div>
</x-website::layouts.main>
