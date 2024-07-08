<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative">
        <div class="flex mb-5 space-x-4">
        <h1 class="text-xl font-semibold min-w-fit">{{ $this->getTitle() }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        @if(!$registerComplete)
                @if (Setting::get('allow_registration'))
                    <form wire:submit='register' class="space-y-4">
                        <div class="grid sm:grid-cols-6 gap-4">
                            <div class="form-control sm:col-span-3 gap-2">
                                <label class="label-text">
                                    {{ __('translation.pagesBlade.registerGivenName') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="input input-sm" wire:model="given_name" required />
                                @error('given_name')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-3 gap-2">
                                <label class="label-text">
                                    {{ __('translation.pagesBlade.registerFamilyName') }}
                                </label>
                                <input type="text" class="input input-sm" wire:model="family_name" />
                                @error('family_name')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-3 gap-2">
                                <label class="label-text">
                                    {{ __('translation.pagesBlade.registerAffiliation') }}
                                </label>
                                <input type="text" class="input input-sm" wire:model="affiliation" />
                                @error('affiliation')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-3 gap-2">
                                <label class="label-text">
                                    {{ __('translation.pagesBlade.registerCountry') }}
                                </label>
                                <select class="select select-sm font-normal" name="country" wire:model='country'>
                                    <option value="none" selected disabled>Select country</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->flag . ' ' . $country->name }}</option>
                                    @endforeach
                                </select>
                                @error('country')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-control sm:col-span-6 gap-2">
                                <label class="label-text">
                                    {{ __('translation.pagesBlade.registerEmail') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="email" class="input input-sm" wire:model="email" />
                                @error('email')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-3 gap-2">
                                <label class="label-text">
                                    {{ __('translation.pagesBlade.registerPassword') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="password" class="input input-sm" wire:model="password" required />
                                @error('password')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control sm:col-span-3 gap-2">
                                <label class="label-text">
                                    {{ __('translation.pagesBlade.registerPasswordConfirmation') }}<span class="text-red-500">*</span>
                                </label>
                                <input type="password" class="input input-sm" wire:model="password_confirmation" required />
                                @error('password_confirmation')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            
                            @if($currentConference)
                            <div class="form-control sm:col-span-6 gap-2">
                                <label class="label-text">{{ __('translation.pagesBlade.registerRegisterAs') }}<span class="text-red-500">*</span></label>
                                @foreach ($roles as $role)
                                    <div class="form-control">
                                        <div class="inline-flex gap-2 items-center cursor">
                                            <input type="checkbox" class="checkbox checkbox-sm" wire:model='selfAssignRoles'
                                                value="{{ $role }}" />
                                            <label class="label-text">{{ $role }}</label>
                                        </div>
                                    </div>
                                @endforeach
                                @error('selfAssignRoles')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            @endif

                            @if(!$currentConference)
                                <div class="col-span-full space-y-4">
                                    <p class="">{{ __('translation.pagesBlade.registerWhichConferencesListedOnThisWebsiteAreYou') }}</p>
                                    @foreach ($conferences as $conference)
                                        <div class="conference form-control gap-2">
                                            <label class="conference-name label-text font-medium">{{ $conference->name }}</label>
                                            @foreach ($roles as $role)
                                                <div class="conference-roles form-control">
                                                    <div class="inline-flex gap-2 items-center cursor">
                                                        <input type="checkbox" name="selfAssignRoles[{{ $conference->id }}]" class="checkbox checkbox-sm" wire:model='selfAssignRoles.{{ $conference->id }}.{{ $role }}'
                                                            value="{{ $role }}" />
                                                        <label class="label-text">{{ $role }}</label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="form-control sm:col-span-6 gap-2">
                                <div class="form-control">
                                    <label class="p-0 label justify-normal gap-2">
                                        <input type="checkbox" class="checkbox checkbox-sm" wire:model="privacy_statement_agree"
                                            required />
                                        <p class="label-text">
                                            {{ __('translation.pagesBlade.registerIAcceptAndApproveAccordingTo') }}<x-website::link :href="$privacyStatementUrl" class="link link-primary link-hover">{{ __('translation.pagesBlade.registerPrivacyStatement') }}</x-website::link>
                                        </p>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                <span class="loading loading-spinner loading-xs" wire:loading></span>
                                {{ __('translation.pagesBlade.registerRegister') }}
                            </button>
                            <x-website::link class="btn btn-outline btn-sm" :href="url('login')">
                                {{ __('translation.pagesBlade.registerLogin') }}
                            </x-website::link>
                        </div>
                    </form>
                @else
                    <p>{{ __('translation.pagesBlade.registerThisConferenceIsCurrentlyClosingUserRegistrations') }}</p>
                @endif
        @else
                <p>{{ __('translation.pagesBlade.registerThankYoForCompletingTheRegistrationWhatWould') }}</p>
                <ul class='list-disc list-inside'> 
                    <li><x-website::link class="link link-primary link-hover" href="{{ $currentConference ? route('filament.conference.pages.profile') : route('filament.administration.pages.profile') }}">{{ __('translation.pagesBlade.registerEditMyProfile') }}</x-website::link></li>
                    <li><x-website::link class="link link-primary link-hover" href="{{ $homeUrl }}">{{ __('translation.pagesBlade.registerContinueBrowsing') }}</x-website::link></li>
                </ul>
        @endif
    </div>
</x-website::layouts.main>
