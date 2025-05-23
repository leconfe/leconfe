@php
    $primaryNavigationItems = app()->getNavigationItems('primary-navigation-menu');
    $userNavigationItems = app()->getNavigationItems('user-navigation-menu');
@endphp

<aside class="flex items-center lg:hidden" x-slide-over>
    <button @@click="toggleSlideOver" class="btn btn-square btn-sm btn-ghost">
        <x-heroicon-o-bars-3 class="h-6 w-6" x-show="!slideOverOpen" x-cloak />
        <x-heroicon-o-x-mark class="h-6 w-6" x-show="slideOverOpen" x-cloak />
    </button>
    <template x-teleport="body">
        <div x-show="slideOverOpen" @@keydown.window.escape="closeSlideOver" class="relative z-[70]">
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
                                class="bg-white border-neutral-100/70 border-r shadow-lg h-svh overflow-y-scroll">
                                <div class="ps-4 py-2 bg-primary text-primary-content flex">
                                    <x-website::logo :headerLogo="$headerLogo"/>
                                    <button @@click="closeSlideOver" class="btn btn-sm btn-square btn-ghost">
                                        <x-heroicon-o-x-mark class="h-6 w-6" />
                                    </button>
                                </div>
                                <div class="flex flex-col justify-between">
                                    @if($primaryNavigationItems->isNotEmpty())
                                        <div class="primary-navigations-menu-mobile">
                                            <ul role="list space-y-2">
                                                @foreach ($primaryNavigationItems as $item)
                                                    @if(!$item->isDisplayed())
                                                        @continue
                                                    @endif
                                                    @if ($item->children->isEmpty())
                                                        <li class="navigation-menu-item relative">
                                                            <x-website::link @class([
                                                                'hover:bg-base-content/10 items-center py-2 px-4 pr-6 text-sm outline-none transition-colors gap-4 w-full flex',
                                                                'text-primary font-semibold' => request()->url() === $item->getUrl(),
                                                                'text-slate-900 font-medium' => request()->url() !== $item->getUrl(),
                                                            ]) :href="$item->getUrl()">
                                                                {{ $item->getLabel() }}
                                                            </x-website::link>
                                                        </li>
                                                    @else
                                                        <li x-data="{ open: false }" class="navigation-menu-item relative">
                                                            <button 
                                                                x-ref="button"
                                                                @@click="open = !open"
                                                                class="hover:bg-base-content/10 py-2 px-4 pr-6 text-sm outline-none transition-colors gap-4 w-full flex items-center justify-between text-slate-900 font-medium"
                                                                >
                                                                <span>{{ $item->getLabel() }}</span>
                                                                <svg :class="{ '-rotate-180': open}"
                                                                    class="transition relative top-[1px] ms-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg"
                                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                                    stroke-linejoin="round" aria-hidden="true">
                                                                    <polyline points="6 9 12 15 18 9"></polyline>
                                                                </svg>
                                                            </button>
                                                            <ul x-show="open" x-collapse class="mt-1">
                                                                @foreach ($item->children as $key => $childItem)
                                                                    <li class="navigation-menu-item relative">
                                                                        <x-website::link @class([
                                                                            'hover:bg-base-content/10 items-center py-2 px-4 pr-6 text-sm outline-none transition-colors gap-4 w-full flex',
                                                                            'text-primary font-semibold' => request()->url() === $item->getUrl(),
                                                                            'text-slate-900 font-medium' => request()->url() !== $item->getUrl(),
                                                                        ]) :href="$item->getUrl()">
                                                                            {{ $childItem->getLabel() }}
                                                                        </x-website::link>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    <div class="user-navigations-menu-mobile">
                                        <ul role="list space-y-2">
                                           @foreach ($userNavigationItems as $item)
                                                @if(!$item->isDisplayed())
                                                    @continue
                                                @endif
                                                @if ($item->children->isEmpty())
                                                    <li class="navigation-menu-item relative">
                                                        <x-website::link @class([
                                                            'hover:bg-base-content/10 items-center py-2 px-4 pr-6 text-sm outline-none transition-colors gap-4 w-full flex',
                                                            'text-primary font-semibold' => request()->url() === $item->getUrl(),
                                                            'text-slate-900 font-medium' => request()->url() !== $item->getUrl(),
                                                        ]) :href="$item->getUrl()">
                                                            {{ $item->getLabel() }}
                                                        </x-website::link>
                                                    </li>
                                                @else
                                                    <li x-data="{ open: false }" class="navigation-menu-item relative">
                                                        <button 
                                                            x-ref="button"
                                                            @@click="open = !open"
                                                            class="hover:bg-base-content/10 py-2 px-4 pr-6 text-sm outline-none transition-colors gap-4 w-full flex items-center justify-between text-slate-900 font-medium"
                                                            >
                                                            <span>{{ $item->getLabel() }}</span>
                                                            <svg :class="{ '-rotate-180': open}"
                                                                class="transition relative top-[1px] ms-1 h-3 w-3" xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round" aria-hidden="true">
                                                                <polyline points="6 9 12 15 18 9"></polyline>
                                                            </svg>
                                                        </button>
                                                        <ul x-show="open" x-collapse class="mt-1">
                                                            @foreach ($item->children as $key => $childItem)
                                                                <li class="navigation-menu-item relative">
                                                                    <x-website::link @class([
                                                                        'hover:bg-base-content/10 items-center py-2 px-4 pr-6 text-sm outline-none transition-colors gap-4 w-full flex',
                                                                        'text-primary font-semibold' => request()->url() === $item->getUrl(),
                                                                        'text-slate-900 font-medium' => request()->url() !== $item->getUrl(),
                                                                    ]) :href="$item->getUrl()">
                                                                        {{ $childItem->getLabel() }}
                                                                    </x-website::link>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</aside>
