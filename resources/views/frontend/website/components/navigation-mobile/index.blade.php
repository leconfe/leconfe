<aside class="flex items-center lg:hidden" x-slide-over>
    <button @@click="toggleSlideOver" class="btn btn-square btn-sm btn-ghost">
        <x-heroicon-o-bars-3 x-show="!slideOverOpen" x-cloak />
        <x-heroicon-o-x-mark x-show="slideOverOpen" x-cloak />
    </button>
    <template x-teleport="body">
        <div x-show="slideOverOpen" @@keydown.window.escape="closeSlideOver" class="relative z-50">
            <div x-show="slideOverOpen" x-transition.opacity.duration.600ms @@click="closeSlideOver"
                class="fixed inset-0 backdrop-blur-[2px]"></div>
            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="fixed inset-y-0 flex max-w-full pr-10">
                        <div x-show="slideOverOpen" @@click.away="closeSlideOver"
                            x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                            x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                            x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                            x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                            class="w-screen max-w-xs">
                            <div
                                class="flex flex-col h-full pb-5 px-4 sm:px-6 overflow-y-scroll bg-white border-r shadow-lg border-neutral-100/70 justify-between">
                                <x-website::navigation-mobile.items :items="$items" />
                                <div class="flex flex-col gap-2">
                                    @if (\Filament\Facades\Filament::getDefaultPanel()->auth()->user())
                                        <x-website::link href="#" :spa="false"
                                            class="btn btn-sm btn-primary rounded px-4 font-normal">{{ __('general.dashboard') }}</x-website::link>
                                    @else
                                        <x-website::link :href="route('livewirePageGroup.website.pages.register')" :spa="false"
                                            class="btn btn-sm btn-primary rounded px-4 font-normal">Register</x-website::link>
                                        <x-website::link :href="route('livewirePageGroup.website.pages.login')" :spa="false"
                                            class="btn btn-sm rounded px-4 font-normal text-gray-900">Login</x-website::link>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</aside>
