<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative space-y-2">
        <div class="flex space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ $announcement->title }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0">
        </div>
        <div class="announcement-information">
            <p class="text-xs text-gray-500">
                {{ $announcement->created_at->format(Setting::get('format_date')) }}
            </p>
        </div>
        @if ($announcement->hasMedia('featured_image'))
            <img class="max-h-48 w-auto"
                src="{{ $announcement->getFirstMedia('featured_image')->getAvailableUrl(['thumb']) }}" alt="">
        @endif
        <div class="user-content">
            {{ new Illuminate\Support\HtmlString($this->announcement->getMeta('content')) }}
        </div>
    </div>
</x-website::layouts.main>
