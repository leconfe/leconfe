<div class="space-y-4">
    @if ($title)
        <x-website::heading-title :title="$title" class="mb-5" />
    @endif
    @if (!empty($description))
        <div class="user-content">
            {!! $description !!}
        </div>
    @endif
    <ol class="relative border-s border-gray-200">
        @foreach ($timelines as $timeline)
            @php
                $formattedDate = Date::parse($timeline['date_start'])->format(Setting::get('format_date'));

                if ($timeline['date_end']) {
                    $formattedDate .= ' - ' . Date::parse($timeline['date_end'])->format(Setting::get('format_date'));
                }
            @endphp

            <li class="mb-10 ms-4 last:mb-0">
                <div
                    class="absolute w-3 h-3 bg-gray-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-gray-900 dark:bg-gray-700">
                </div>
                <time class="mb-1 text-sm font-normal leading-none text-gray-400">
                    {{ $formattedDate }}
                </time>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $timeline['name'] }}</h3>
                @if ($timeline['description'])
                    <p class="text-base font-normal text-gray-500 dark:text-gray-400">
                        {{ $timeline['description'] }}
                    </p>
                @endif
            </li>
        @endforeach
    </ol>
</div>
