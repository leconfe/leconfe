<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative space-y-2">
        <div class="flex space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ $this->announcement->title }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        <div class="announcement-information">
            @php
                $announcementCreatedDate = $announcement->created_at->startOfDay();
                $diffInDays = $announcementCreatedDate->diffInDays(today());
            @endphp
            <div class="flex flex-wrap justify-between">
                <p class="text-xs text-gray-500">
                    @if ($diffInDays > 0)
                        {{ __('translation.pagesBlade.annoucementPublished') }} {{ $diffInDays }} {{ __('translation.pagesBlade.annoucementDaysAgo') }}
                    @else
                        {{ __('translation.pagesBlade.annoucementPublishedToday') }}
                    @endif
                </p>
                <p class="text-gray-500 text-xs">
                    @if ($user = $announcement->user)
                        {{ __('translation.pagesBlade.annoucementBy') }} {{ $user->fullName }}
                    @else
                        {{ __('translation.pagesBlade.annoucementByAdmin') }}
                    @endif
                </p>
            </div>
        </div>
        <div class="text-xs text-gray-500 font-medium">
            {{ $this->announcement->created_at->format(Setting::get('format_date')) }}</div>

        @can('update', $announcement)
            <div class="flex">
                <a class="ms-auto btn btn-primary btn-xs"
                    href="{{ route('filament.conference.resources.conferences.announcements.edit', ['record' => $announcement->getKey()]) }}">
                    <x-heroicon-s-pencil-square class="h-4 w-4" /> {{ __('translation.button.edit') }}
                </a>
            </div>
        @endcan
        
        @if ($announcement->hasMedia('featured_image'))
            <img class="max-h-40 w-auto"
                src="{{ $announcement->getFirstMedia('featured_image')->getAvailableUrl(['thumb']) }}" alt="">
        @endif
        <div class="user-content">
            {{ new Illuminate\Support\HtmlString($this->announcement->getMeta('content')) }}
        </div>
    </div>
</x-website::layouts.main>
