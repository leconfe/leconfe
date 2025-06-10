<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <x-website::heading-title :title="$title" class="mb-5" />

    <div class="flex flex-col gap-10">
        @foreach ($this->staticPage->getBlocks() as $block)
            {!! $block->render() !!}
        @endforeach
    </div>
</x-website::layouts.main>
