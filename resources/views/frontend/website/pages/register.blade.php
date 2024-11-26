<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ $this->getTitle() }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        @if (!$registerComplete)
            @if ($allowRegistration)
                <form wire:submit='register' class="space-y-4">
                    @error('throttle')
                        <div class="text-sm text-red-600">
                            {{ $message }}
                        </div>
                    @enderror
                    <div class="grid gap-4 sm:grid-cols-6">
                        <div class="gap-2 form-control sm:col-span-3">
                            <label class="label-text">
                                {{ __('general.given_name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" class="input input-sm" wire:model="given_name" required />
                            @error('given_name')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="gap-2 form-control sm:col-span-3">
                            <label class="label-text">
                                {{ __('general.family_name') }}
                            </label>
                            <input type="text" class="input input-sm" wire:model="family_name" />
                            @error('family_name')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="gap-2 form-control sm:col-span-3">
                            <label class="label-text">
                                {{ __('general.affiliation') }}
                            </label>
                            <input type="text" class="input input-sm" wire:model="affiliation" />
                            @error('affiliation')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="gap-2 form-control sm:col-span-3">
                            <label class="label-text">
                                {{ __('general.country') }}
                            </label>
                            <select class="font-normal select select-sm" name="country" wire:model='country'>
                                <option value="none" selected disabled>{{ __('general.select_country') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->flag . ' ' . $country->name }}</option>
                                @endforeach
                            </select>
                            @error('country')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="gap-2 form-control sm:col-span-6">
                            <label class="label-text">
                                {{ __('general.phone') }}
                            </label>
                            <input type="tel" class="input input-sm" wire:model="phone" />
                            @error('phone')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                            <p class="text-xs text-gray-500">{{ __('general.phone_format_international') }}</p>
                        </div>
                        <div class="gap-2 form-control sm:col-span-6">
                            <label class="label-text">
                                {{ __('general.email') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="email" class="input input-sm" wire:model="email" />
                            @error('email')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="gap-2 form-control sm:col-span-3">
                            <label class="label-text">
                                {{ __('general.password') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="password" class="input input-sm" wire:model="password" required />
                            @error('password')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="gap-2 form-control sm:col-span-3">
                            <label class="label-text">
                                {{ __('general.password_confirmation') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="password" class="input input-sm" wire:model="password_confirmation" required />
                            @error('password_confirmation')
                                <div class="text-sm text-red-600">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        @if (isset($scheduledConference) && $scheduledConference && !empty($roles))
                            <div class="gap-2 form-control sm:col-span-6">
                                <label class="label-text">{{ __('general.register_as') }} <span class="text-red-500">*</span></label>
                                @foreach ($roles as $role)
                                    <div class="form-control">
                                        <div class="inline-flex items-center gap-2 cursor">
                                            <input type="checkbox" class="checkbox checkbox-sm"
                                                wire:model='selfAssignRoles' value="{{ $role }}" />
                                            <label class="label-text">{{ $role }}</label>
                                        </div>
                                    </div>
                                @endforeach
                                @error('selfAssignRoles')
                                    <div class="text-sm text-red-600">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        @endif

                        @if (isset($scheduledConference) && !$scheduledConference)
                            <div class="space-y-4 col-span-full">
                                <p class="">{{ __('general.which_conference_interested_for') }}</p>
                                @foreach ($conferences as $conference)
                                    <div class="gap-2 conference form-control">
                                        <label
                                            class="font-medium conference-name label-text">{{ $conference->name }}</label>
                                        @foreach ($roles as $role)
                                            <div class="conference-roles form-control">
                                                <div class="inline-flex items-center gap-2 cursor">
                                                    <input type="checkbox"
                                                        name="selfAssignRoles[{{ $conference->id }}]"
                                                        class="checkbox checkbox-sm"
                                                        wire:model='selfAssignRoles.{{ $conference->id }}.{{ $role }}'
                                                        value="{{ $role }}" />
                                                    <label class="label-text">{{ $role }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="gap-2 form-control sm:col-span-6">
                            <div class="form-control">
                                <label class="gap-2 p-0 label justify-normal">
                                    <input type="checkbox" class="checkbox checkbox-sm"
                                        wire:model="privacy_statement_agree" required />
                                    <div class="label-text">
                                        {!! __('general.privacy_statement_agree', ['url' => $privacyStatementUrl]) !!}
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                            <span class="loading loading-spinner loading-xs" wire:loading></span>
                            {{ __('general.register') }}
                        </button>
                        <x-website::link class="btn btn-outline btn-sm" :href="$loginUrl">
                            {{ __('general.login') }}
                        </x-website::link>
                    </div>
                </form>
            @else
                <p>{{ __('general.registration_closed') }}</p>
            @endif
        @else
            <p>{{ __('general.registration_complete_message') }}</p>
            <ul class='list-disc list-inside'>
                <li>
                    <x-website::link class="link link-primary link-hover" href="{{ route('filament.scheduledConference.pages.profile') }}">
                        {{ __('general.edit_my_profile') }}
                    </x-website::link>
                </li>
                <li>
                    <x-website::link class="link link-primary link-hover" href="{{ $homeUrl }}">
                        {{ __('general.continue_browsing') }}
                    </x-website::link>
                </li>
            </ul>
        @endif
    </div>
</x-website::layouts.main>
