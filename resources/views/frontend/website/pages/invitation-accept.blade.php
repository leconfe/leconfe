<x-website::layouts.main class="space-y-4">
    @if($errorMessage)
        <div class="mb-6">
            <x-website::breadcrumbs :breadcrumbs="[url('/') => __('general.home'), 'Accept Invitation']" />
        </div>

        <div class="space-y-3">
            <h1 class="text-xl font-semibold">Accept Invitation</h1>
            <p class="text-sm text-red-600">{{ $errorMessage }}</p>
            <x-website::link class="btn btn-outline btn-sm" :href="$loginUrl">
                {{ __('general.login') }}
            </x-website::link>
        </div>
    @elseif($nextUrl)
        <script>
            window.location.href = @js($nextUrl);
        </script>
    @endif
</x-website::layouts.main>
