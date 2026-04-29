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
                    <img src="{{ asset('logo.png') }}" alt="{{ __('general.logo') }}" class="size-5 object-contain" />
                </div>
                <h2 class="mb-4 text-3xl font-extrabold tracking-tight sm:text-4xl">
                    {!! __('scheduled_conference.welcome_message_title', ['conference' => $scheduledConference ? $scheduledConference->title : __('general.conference')]) !!}
                </h2>
                <p class="text-lg leading-relaxed text-primary-100">
                    @if ($isAssignRole)
                        {{ __('scheduled_conference.role_selection_description') }}
                    @elseif ($scheduledConference?->date_start && $scheduledConference?->date_end)
                                        {{ __('scheduled_conference.welcome_overview_description', [
                            'start_date' => $scheduledConference->date_start->format(\App\Facades\Setting::get('format_date')),
                            'end_date' => $scheduledConference->date_end->format(\App\Facades\Setting::get('format_date')),
                        ]) }}
                    @endif
                </p>
            </div>

        </div>

        <!-- Right Side: Role Selection List -->
        <div class="flex flex-col p-6 sm:p-8 lg:w-3/5 lg:p-12">
            @if ($isAssignRole)
                <h3 class="mb-6 text-xl font-bold text-gray-950 dark:text-white lg:hidden">
                    {{ __('general.select_role') }}
                </h3>
                <div class="flex-1 space-y-4">
                    @forelse ($roleCards as $role)
                        @php
                            $checkedBorderClass = match ($role['color']) {
                                'primary' => 'peer-checked:border-primary-500 peer-checked:bg-primary-50/50 dark:peer-checked:bg-primary-900/20',
                                'warning' => 'peer-checked:border-warning-500 peer-checked:bg-warning-50/50 dark:peer-checked:bg-warning-900/20',
                                'success' => 'peer-checked:border-success-500 peer-checked:bg-success-50/50 dark:peer-checked:bg-success-900/20',
                                default => 'peer-checked:border-gray-500 peer-checked:bg-gray-50/50 dark:peer-checked:bg-gray-900/20',
                            };

                            $iconClass = match ($role['color']) {
                                'primary' => 'text-primary-600 dark:text-primary-400',
                                'warning' => 'text-warning-600 dark:text-warning-400',
                                'success' => 'text-success-600 dark:text-success-400',
                                default => 'text-gray-600 dark:text-gray-400',
                            };

                            $titleHoverClass = match ($role['color']) {
                                'primary' => 'group-hover:text-primary-600 dark:group-hover:text-primary-400',
                                'warning' => 'group-hover:text-warning-600 dark:group-hover:text-warning-400',
                                'success' => 'group-hover:text-success-600 dark:group-hover:text-success-400',
                                default => 'group-hover:text-gray-600 dark:group-hover:text-gray-400',
                            };

                            $checkedIconClass = match ($role['color']) {
                                'primary' => 'peer-checked:border-primary-500 peer-checked:bg-primary-500 peer-checked:text-white',
                                'warning' => 'peer-checked:border-warning-500 peer-checked:bg-warning-500 peer-checked:text-white',
                                'success' => 'peer-checked:border-success-500 peer-checked:bg-success-500 peer-checked:text-white',
                                default => 'peer-checked:border-gray-500 peer-checked:bg-gray-500 peer-checked:text-white',
                            };
                        @endphp

                        <label
                            class="group relative flex cursor-pointer items-center rounded-2xl border-2 border-transparent bg-gray-50 p-4 transition-all hover:bg-gray-100 dark:bg-gray-800/60 dark:hover:bg-gray-800 sm:p-5">
                            <input type="radio" wire:model.live="formData.role" value="{{ $role['name'] }}"
                                class="peer sr-only" />

                            {{-- Border aktif --}}
                            <div
                                class="pointer-events-none absolute inset-0 rounded-2xl border-2 border-transparent transition-colors {{ $checkedBorderClass }}">
                            </div>

                            {{-- Icon --}}
                            <div
                                class="relative flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-white shadow-sm transition-transform group-hover:scale-105 group-hover:shadow-md dark:bg-gray-800 {{ $iconClass }}">
                                <x-dynamic-component :component="'heroicon-o-' . $role['icon']" class="h-7 w-7" />
                            </div>

                            {{-- Text --}}
                            <div class="relative ml-4 flex-1 sm:ml-5">
                                <h3
                                    class="text-lg font-bold text-gray-900 transition-colors dark:text-white {{ $titleHoverClass }}">
                                    {{ $role['name'] }}
                                </h3>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $role['description'] }}
                                </p>
                            </div>

                            {{-- Check icon --}}
                            <div
                                class="relative ml-4 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 border-gray-300 bg-white text-transparent transition-colors dark:border-gray-600 dark:bg-gray-700 {{ $checkedIconClass }}">
                                <x-heroicon-m-check class="h-5 w-5" />
                            </div>

                        </label>
                    @empty
                        <div
                            class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            {{ __('scheduled_conference.no_self_assignable_roles_available') }}
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


            @else
                <div class="mb-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ __('general.discover') }}
                    </h3>
                </div>

                <div class="grid gap-4">
                    @role(App\Models\Enums\UserRole::Author->value)
                    <div
                        class="rounded-2xl border border-primary-200/70 bg-gradient-to-r from-primary-50 to-white p-5 shadow-sm transition-all hover:shadow-md dark:border-primary-900/40 dark:from-primary-950/40 dark:to-gray-900">
                        <a href="{{ $submissionUrl }}" class="flex items-start gap-4">
                            <div
                                class="mt-0.5 inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                                <x-heroicon-o-document-text class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <h4
                                    class="text-sm font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    {{ __('scheduled_conference.submit_paper') }}
                                </h4>
                                <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
                                    {!! __('scheduled_conference.welcome_message_submit_paper') !!}
                                </p>
                            </div>
                        </a>
                    </div>
                    @endrole

                    @role(App\Models\Enums\UserRole::Participant->value)
                    <div
                        class="rounded-2xl border border-success-200/70 bg-gradient-to-r from-success-50 to-white p-5 shadow-sm transition-all hover:shadow-md dark:border-success-900/40 dark:from-success-950/30 dark:to-gray-900">
                        <a href="{{ $scheduledConference->isParticipantRegistrationEnabled() ? (auth()->user()->isRegisteredAsParticipant() ? $participantPaymentUrl : $participantRegistrationUrl) : '#' }}"
                            class="flex items-start gap-4">
                            <div
                                class="mt-0.5 inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-success-100 text-success-700 dark:bg-success-900/50 dark:text-success-300">
                                <x-heroicon-o-user-group class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                @if ($scheduledConference->isParticipantRegistrationEnabled())
                                    @if (auth()->user()->isRegisteredAsParticipant())
                                        <h4
                                            class="text-sm font-semibold uppercase tracking-wide text-success-700 dark:text-success-300">
                                            {{ __('general.participant') }}
                                        </h4>
                                        <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
                                            {!! __('scheduled_conference.welcome_message_registered_non_presenter', ['participant_detail_link' => $participantPaymentUrl]) !!}
                                        </p>
                                    @else
                                        <h4
                                            class="text-sm font-semibold uppercase tracking-wide text-success-700 dark:text-success-300">
                                            {{ __('general.registration') }}
                                        </h4>
                                        <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
                                            {!! __('scheduled_conference.welcome_message_non_presenter', ['register_link' => $participantRegistrationUrl]) !!}
                                        </p>
                                    @endif
                                @else
                                    <h4
                                        class="text-sm font-semibold uppercase tracking-wide text-success-700 dark:text-success-300">
                                        {{ __('general.registration') }}
                                    </h4>
                                    <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-200">
                                        {{ __('general.registration_closed') }}
                                    </p>
                                @endif
                            </div>
                        </a>
                    </div>
                    @endrole
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>