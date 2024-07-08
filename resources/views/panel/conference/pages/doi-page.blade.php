<x-filament-panels::page x-data="{ activeTab: 'submissions' }">
    <x-filament::tabs>
        @if($articlesDoiEnabled)
            <x-filament::tabs.item
                alpine-active="activeTab === 'submissions'"
                x-on:click="activeTab = 'submissions'"
                >
                {{ __('translation.subsmissionDOI.titleSubmissions') }}
            </x-filament::tabs.item>
        @endif

        @if ($proceedingsDoiEnabled)
            <x-filament::tabs.item
            alpine-active="activeTab === 'proceedings'"
            x-on:click="activeTab = 'proceedings'"
            >
                {{ __('translation.subsmissionDOI.titleProceedings') }}
            </x-filament::tabs.item>
        @endif
    </x-filament::tabs>

    @if($articlesDoiEnabled)
        <div x-show="activeTab === 'submissions'">
            @livewire(App\Panel\Conference\Livewire\SubmissionDOI::class)
        </div>
    @endif

    @if ($proceedingsDoiEnabled)
        <div x-show="activeTab === 'proceedings'">
            @livewire(App\Panel\Conference\Livewire\ProceedingDOI::class)
        </div>
    @endif

</x-filament-panels::page>
