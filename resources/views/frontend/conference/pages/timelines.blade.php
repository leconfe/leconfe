<x-website::layouts.main>
    <section class="p-5">
        <div class="flex flex-wrap">
            <div class="flex flex-col basis-1/2">
                <div class="flex flex-col space-y-1 mb-3">
                    <h2 class="text-heading">{{ __('translation.pagesBlade.timeLines') }}</h2>
                    <div class="border border-primary w-12"></div>
                </div>

                <ol class="border-l border-neutral-300 dark:border-neutral-500 flex flex-col gap-y-6">
                    @forelse ($timelines as $timeline)
                        <li class="flex flex-col gap-y-2">
                            <div class="flex-start flex items-center pt-3">
                                <div
                                    class="-ml-[5px] mr-3 h-[9px] w-[9px] rounded-full bg-neutral-300 dark:bg-neutral-500">
                                </div>
                                <p class="text-sm font-semibold">{{ $timeline->title }}</p>
                            </div>
                            <p class="text-sm text-gray-500 ps-4">
                                {{ date(Setting::get('format_date'), strtotime($timeline->date)) }}
                            </p>
                            <time class="text-xs ps-4 text-gray-500">{{ $timeline->subtitle }}</time>

                            <div class="ps-4 inline-flex gap-2 flex-wrap">
                                @foreach ($timeline->roles as $role)
                                    @php
                                        $badgeRole = '';
                                        $badgeRole = match ($role) {
                                            'Author' => 'author-badge',
                                            'Editor' => 'editor-badge',
                                            'Reviewer' => 'reviewer-badge',
                                            'Participant' => 'participant-badge',
                                            default => 'participant-badge',
                                        };
                                    @endphp
                                    <div class="{{ $badgeRole }}">{{ $role }}</div>
                                @endforeach
                            </div>
                        </li>
                    @empty
                    @endforelse
                </ol>
            </div>
        </div>

    </section>
</x-website::layouts.main>
