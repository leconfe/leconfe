<x-website::layouts.main class="space-y-2">
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ __('translation.pagesBlade.login') }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        <form wire:submit='login' class="space-y-4">
            <div class="form-control sm:col-span-6 gap-2">
                <label class="label-text">
                    {{ __('translation.pagesBlade.loginEmail') }} <span class="text-red-500">*</span>
                </label>
                <input type="email" name="email" class="input input-sm" wire:model="email" />
                @error('email')
                    <div class="text-red-600 text-sm">
                        {{ $message }}
                    </div>
                @enderror
            </div>
            <div class="form-control sm:col-span-3 gap-2">
                <label class="label-text">
                    {{ __('translation.pagesBlade.loginPassword') }}<span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" class="input input-sm" wire:model="password" required />
                @error('password')
                    <div class="text-red-600 text-sm">
                        {{ $message }}
                    </div>
                @enderror
            </div>
            <div class="form-control">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model='remember' class="checkbox checkbox-sm" />
                    <span class="label-text">{{ __('translation.pagesBlade.loginRememberMe') }}</span>
                </label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading></span>
                    {{ __('translation.pagesBlade.login') }}
                </button>
                <x-website::link class="btn btn-outline btn-sm" :href="$registerUrl">
                    {{ __('translation.pagesBlade.loginRegister') }}
                </x-website::link>
            </div>
        </form>
    </div>

</x-website::layouts.main>
