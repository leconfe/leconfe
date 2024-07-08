<div class="space-y-6">
    <div class="filament-forms-card-component p-6 bg-white dark:bg-gray-900 rounded-xl border dark:border-gray-800">
        <div class="grid grid-cols-1 filament-forms-component-container gap-6">
            <div class="col-span-full">
                <div id="upload-files" class="filament-forms-section-component grid grid-cols-1 md:grid-cols-2">
                    <div
                        class="filament-forms-section-header-wrapper flex rtl:space-x-reverse overflow-hidden rounded-t-xl min-h-[56px] pr-6 pb-4">
                        <div class="filament-forms-section-header flex-1 space-y-1">
                            <h3 class="font-bold tracking-tight pointer-events-none flex flex-row items-center text-xl">
                               {{__('translation.submissions.authorStepContributors')}}
                            </h3>

                            <p class="text-gray-500 text-base">
                                {{__('translation.submissions.authorStepPleaseSubmission')}}
                            </p>
                        </div>

                    </div>

                    <div class="filament-forms-section-content-wrapper">
                        @error('errors')
                            <div class="flex p-4 mb-4 text-sm text-danger-800 border border-danger-300 rounded-xl bg-danger-50 dark:bg-gray-800 dark:text-danger-400 dark:border-danger-800"
                                role="alert">
                                <svg aria-hidden="true" class="flex-shrink-0 inline w-5 h-5 mr-3" fill="currentColor"
                                    viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span class="sr-only">{{__('translation.submissions.authorStepNewInfo')}}</span>
                                <div>
                                    {{ $message }}
                                </div>
                            </div>
                        @enderror
                        @livewire(App\Panel\Conference\Livewire\Submissions\Components\ContributorList::class, ['submission' => $this->record, 'lazy' => true])
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="flex items-center justify-between">
        <div>
            <x-filament::button icon="heroicon-o-chevron-left" x-show="! isFirstStep()" x-cloak
                x-on:click="previousStep" color="gray" size="sm">
                {{ __('translation.button.previous') }}
            </x-filament::button>
        </div>

        <div>
            <x-filament::button icon="heroicon-o-chevron-right" icon-position="after" x-show="! isLastStep()" x-cloak
                wire:click="nextStep" wire:loading.class.delay="opacity-70 cursor-wait" size="sm">
                {{ __('translation.button.next') }}
            </x-filament::button>
        </div>
    </div>
</div>
