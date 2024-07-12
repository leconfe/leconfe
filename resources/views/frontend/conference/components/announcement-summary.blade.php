@props(['announcement'])

<div class="announcement-summary sm:flex sm:flex-row gap-x-2 p-1">
    @if ($announcement->hasMedia('featured_image'))
    <img class="max-h-36 w-auto"
        src="{{ $announcement->getFirstMedia('featured_image')->getAvailableUrl(['thumb']) }}" alt="">
    @endif
    <div class="leading-normal space-y-1">
        <h3 class="announcement-title text-lg tracking-tight text-gray-900">
            <a href="{{ route('livewirePageGroup.conference.pages.announcement-page', ['announcement' => $announcement->id]) }}" class="link link-hover">
                {{ $announcement->title }}
            </a>    
        </h3>
        <div class="announcement-date text-xs font-medium text-gray-500">
            {{ $announcement->created_at->format(Setting::get('format_date')) }}
        </div>
        <p class="summary text-gray-800 text-sm">
            {{ $announcement->getMeta('summary') }}
        </p>
    </div>
</div>


