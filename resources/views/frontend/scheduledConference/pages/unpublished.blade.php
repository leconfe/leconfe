<x-website::layouts.app :title="__('scheduled_conference.unpublished_title')">
    <x-website::layouts.main :show-sidebar="false">
        <section class="mx-auto max-w-3xl space-y-4 py-16 text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-primary-700">
                {{ __('scheduled_conference.unpublished_status') }}
            </p>
            <h1 class="text-3xl font-semibold text-gray-950">
                {{ $scheduledConference->title }}
            </h1>
            <p class="text-base text-gray-700">
                {{ __('scheduled_conference.unpublished_description') }}
            </p>
        </section>
    </x-website::layouts.main>
</x-website::layouts.app>
