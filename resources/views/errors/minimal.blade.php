<x-website::layouts.app title="{{ $title }}">
    <x-website::layouts.main>
        <div class="text-center">
            <h1 class="mb-4 text-6xl font-semibold text-red-500">
                {{ $code }}
            </h1>
            <p class="mb-4 text-lg text-gray-600">{{ $message }}</p>
            <x-website::link class="btn btn-primary btn-sm" :href="$homeUrl">
              {{  __('translation.errorsBlade.minimalHome')}}
            </x-website::link>
        </div>
    </x-website::layouts.main>
</x-website::layouts.app>
