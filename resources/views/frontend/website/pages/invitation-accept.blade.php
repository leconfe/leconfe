<x-website::layouts.main class="space-y-4">
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="[url('/') => __('general.home'), 'Accept Invitation']" />
    </div>

    <div class="space-y-3">
        <h1 class="text-xl font-semibold">Accept Invitation</h1>

        @if($errorMessage)
            <p class="text-sm text-red-600">{{ $errorMessage }}</p>
            <x-website::link class="btn btn-outline btn-sm" :href="$loginUrl">
                {{ __('general.login') }}
            </x-website::link>
        @else
            <p class="text-sm text-gray-700">Please wait while we process your invitation.</p>
            @if($nextUrl)
                <x-website::link class="btn btn-outline btn-sm" :href="$nextUrl">
                    Continue
                </x-website::link>
                <script>
                    window.location.href = @js($nextUrl);
                </script>
            @endif
        @endif
    </div>
</x-website::layouts.main>
