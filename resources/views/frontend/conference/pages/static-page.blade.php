<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ $title }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        @if ($content)
            <div class="user-content">
                {{ new Illuminate\Support\HtmlString($content) }}
            </div>
        @else
            <div>
                {{ __('translation.pagesBlade.staticPage') }}
            </div>
        @endif
    </div>
</x-website::layouts.main>
