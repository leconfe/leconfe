@php
    $hasGuidanceModalContent = $this->hasGuidanceModalContent();
    $hasReviewGuidelines = $this->hasReviewGuidelines();
    $hasCompetingInterests = $this->hasCompetingInterests();
@endphp

<x-filament-panels::page x-on:show-guidelines="$dispatch('open-modal', {'id': 'guidelines'})"  x-data="
  {  
    hasGuidanceModalContent: {{ $hasGuidanceModalContent ? 'true' : 'false' }},
    autoShowGuidelinesEnable() {
        return localStorage.getItem('autoShowGuidelines') != 0;
    },
    toggleAutoShowGuidelines() {
        localStorage.setItem('autoShowGuidelines', this.autoShowGuidelinesEnable() ? 0 : 1);
    },
    init() {
        if(this.hasGuidanceModalContent && this.autoShowGuidelinesEnable()) {
            $nextTick(() => {
                $dispatch('open-modal', {'id': 'guidelines'})
            });
        }
    }
}
    ">
    @if($review->reviewSubmitted())
        <x-shout::shout type="success" color="success" icon="heroicon-o-check-circle">
            {{ __('general.review_thank_you_message') }}
        </x-shout::shout>
    @endif
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-8 space-y-4">
            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerAssignedFiles::class, ['record' => $review])
            
            <form>
                {{ $this->form }}
            </form>
            
            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerFiles::class, ['record' => $review, 'viewOnly' => $review->reviewSubmitted()])
            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\Discussions\PeerReviewDiscussionTopic::class, ['submission' => $record, 'stage' => App\Models\Enums\SubmissionStage::PeerReview, 'lazy' => true])

            @if(!$review->reviewSubmitted())
                <div class="flex items-center gap-3">
                    {{ $this->submitReviewAction() }}
                    {{ $this->saveForLaterAction() }}
                </div>
            @endif
        </div>
        <div class="col-span-4 space-y-4 self-start sticky top-20">
            {{ $this->infolist }}
        </div>
    </div>
    @if($hasGuidanceModalContent)
    <x-filament::modal id="guidelines" width="2xl" :close-by-clicking-away="false" >
        <x-slot name="heading">
            <h1 class="text-xl font-bold">
                {{ __('general.review_guidelines_and_competing_interests') }}
            </h1>
        </x-slot>
        <x-slot name="description">
            {{ __('general.review_guidelines_modal_description') }}
        </x-slot>
        <div class="flex flex-col space-y-4">
            @if($hasReviewGuidelines)
            <div>
                <h2 class="text-lg font-bold">
                    {{ __('general.review_guidelines') }}
                </h2>
                {!! $currentScheduledConference->getMeta('review_guidelines') !!}
            </div>
            @endif
            @if($hasCompetingInterests)
            <div>
                <h2 class="text-lg font-bold">
                    {{ __('general.competing_interests') }}
                </h2>
                {!! $currentScheduledConference->getMeta('competing_interests') !!}
            </div>
            @endif

            <div class="flex items-center mb-4">
                <input id="default-checkbox" type="checkbox" value="" class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" :checked="!autoShowGuidelinesEnable()" x-on:change="toggleAutoShowGuidelines()">
                <label for="default-checkbox" class="ms-2 text-sm text-gray-900 dark:text-gray-300">
                    {{ __('general.review_guidelines_understood') }}
                </label>
            </div>

            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {'id': 'guidelines'})">
                    {{ __('general.close') }}
                </x-filament::button>
            </x-slot>
        </div>
    </x-filament::modal>
    @endif
</x-filament-panels::page>
