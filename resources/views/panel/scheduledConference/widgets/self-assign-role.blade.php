<x-filament-widgets::widget>
    <div
        class="flex flex-col overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-gray-950/5 transition-all duration-300 dark:bg-gray-900 dark:ring-white/10 lg:flex-row">

        <!-- Left Side: Welcome & Context Banner -->
        <div
            class="relative flex flex-col justify-between overflow-hidden bg-gradient-to-br from-primary-600 to-primary-900 p-8 text-white lg:w-2/5 lg:p-12">
            <!-- Decorative elements -->
            <div class="absolute -left-20 -top-24 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-24 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>

            <div class="relative z-10">
                <div
                    class="mb-6 inline-flex rounded-2xl bg-white/20 p-3 shadow-inner shadow-white/10 ring-1 ring-white/30 backdrop-blur-md">
                    <img src="{{ asset('logo.png') }}" alt="{{ __('general.logo') }}"
                        class="h-10 w-10 object-contain" />
                </div>
                <h2 class="mb-4 text-3xl font-extrabold tracking-tight sm:text-4xl">
                    {!! __('general.welcome_message_title', ['conference' => $scheduledConference ? $scheduledConference->title : __('general.conference')]) !!}
                </h2>
                <p class="text-lg leading-relaxed text-primary-100">
                    {{ __('general.role_selection_description') }}
                </p>
            </div>

            <div class="relative z-10 mt-10 hidden lg:block">
                <div
                    class="flex items-center gap-3 rounded-xl bg-black/20 p-4 text-sm font-medium text-primary-100 backdrop-blur-sm">
                    <x-heroicon-m-information-circle class="h-6 w-6 text-primary-300" />
                    <span>{{ __('general.multiple_roles_notice') }}</span>
                </div>
            </div>
        </div>

        <!-- Right Side: Role Selection List -->
        <div class="flex flex-col p-6 sm:p-8 lg:w-3/5 lg:p-12">
            <h3 class="mb-6 text-xl font-bold text-gray-950 dark:text-white lg:hidden">
                {{ __('general.select_your_roles') }}</h3>
            <div class="flex-1 space-y-4">
                @forelse ($roleCards as $roleCard)
                    <label
                        class="group relative flex cursor-pointer items-center rounded-2xl border-2 border-transparent bg-gray-50 p-4 transition-all hover:bg-gray-100 dark:bg-gray-800/60 dark:hover:bg-gray-800 sm:p-5">
                        <input type="checkbox" wire:model.live="formData.roles" value="{{ $roleCard['name'] }}"
                            class="peer sr-only" />

                        <div
                            class="pointer-events-none absolute inset-0 rounded-2xl border-2 border-transparent transition-colors {{ $roleCard['checkedRingClass'] }}">
                        </div>

                        <div
                            class="relative flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-white shadow-sm transition-transform group-hover:scale-105 group-hover:shadow-md dark:bg-gray-800 {{ $roleCard['iconClass'] }}">
                            <x-dynamic-component :component="'heroicon-o-' . $roleCard['icon']" class="h-7 w-7" />
                        </div>

                        <div class="relative ml-4 flex-1 sm:ml-5">
                            <h3
                                class="text-lg font-bold text-gray-900 transition-colors dark:text-white {{ $roleCard['titleClass'] }}">
                                {{ $roleCard['name'] }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $roleCard['description'] }}
                            </p>
                        </div>

                        <div
                            class="relative ml-4 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 border-gray-300 bg-white text-transparent transition-colors dark:border-gray-600 dark:bg-gray-700 peer-checked:text-white {{ $roleCard['checkClass'] }}">
                            <x-heroicon-m-check class="h-5 w-5" />
                        </div>
                    </label>
                @empty
                    <div
                        class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        {{ __('general.no_self_assignable_roles_available') }}
                    </div>
                @endforelse

            </div>

            <!-- Footer Action -->
            <div class="mt-8 flex items-center justify-between border-t border-gray-100 pt-6 dark:border-gray-800">
                <x-filament::button type="button" size="lg" color="primary" wire:click="submitRoles"
                    class="ml-auto w-full rounded-xl px-8 py-3 shadow-md transition-all hover:shadow-lg lg:w-auto">
                    {{ __('general.continue') }}
                    <x-heroicon-m-arrow-right class="ml-2 inline h-5 w-5" />
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>