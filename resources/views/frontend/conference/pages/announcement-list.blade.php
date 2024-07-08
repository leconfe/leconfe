<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ __('translation.pagesBlade.annoucement') }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        <div class="overflow-y-auto space-y-2">
            @forelse ($announcements as $announcement)
                <x-conference::announcement-summary :announcement="$announcement" />
            @empty
                <div>
                    {{ __('translation.pagesBlade.annoucementList') }}
                </div>
            @endforelse
        </div>
    </div>
    
</x-website::layouts.main>
