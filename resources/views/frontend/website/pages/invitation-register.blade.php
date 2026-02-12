<x-website::layouts.main class="space-y-2">
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="[url('/') => __('general.home'), 'Invitation Registration']" />
    </div>

    <div class="relative">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">Invitation Registration</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>

        <p class="mb-4 text-sm text-gray-700">
            You were invited as <strong>{{ $invitation->role_name }}</strong> for <strong>{{ $invitation->conference?->name ?? app()->getSite()->getMeta('name') }}</strong>.
        </p>

        <form wire:submit="register" class="space-y-4">
            <div class="gap-2 form-control sm:col-span-6">
                <label class="label-text">{{ __('general.given_name') }} <span class="text-red-500">*</span></label>
                <input type="text" class="input input-sm max-w-md" wire:model="given_name" required />
                @error('given_name')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="gap-2 form-control sm:col-span-6">
                <label class="label-text">{{ __('general.family_name') }}</label>
                <input type="text" class="input input-sm max-w-md" wire:model="family_name" />
                @error('family_name')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="gap-2 form-control sm:col-span-6">
                <label class="label-text">{{ __('general.email') }}</label>
                <input type="email" class="input input-sm max-w-md" value="{{ $invitation->email }}" disabled />
            </div>

            <div class="gap-2 form-control sm:col-span-6">
                <label class="label-text">{{ __('general.password') }} <span class="text-red-500">*</span></label>
                <input type="password" class="input input-sm max-w-md" wire:model="password" required />
                @error('password')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="gap-2 form-control sm:col-span-6">
                <label class="label-text">{{ __('general.password_confirmation') }} <span class="text-red-500">*</span></label>
                <input type="password" class="input input-sm max-w-md" wire:model="password_confirmation" required />
            </div>

            <div class="gap-2 form-control sm:col-span-6">
                <label class="gap-2 p-0 label justify-normal">
                    <input type="checkbox" class="checkbox checkbox-sm" wire:model="privacy_statement_agree" />
                    <div class="label-text">
                        @if($privacyStatementUrl)
                            {!! __('general.privacy_statement_agree', ['url' => $privacyStatementUrl]) !!}
                        @else
                            {{ __('general.privacy_statement_agree_without_link') }}
                        @endif
                    </div>
                </label>
                @error('privacy_statement_agree')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading></span>
                    Create Account & Accept Invitation
                </button>
            </div>
        </form>
    </div>
</x-website::layouts.main>
