<div class="space-y-6">
    <div class="p-6 bg-white border filament-forms-card-component dark:bg-gray-900 rounded-xl dark:border-gray-800">
        <div class="grid grid-cols-1 gap-6 filament-forms-component-container">
            <div class="col-span-full">
                <div id="upload-files" class="grid grid-cols-1 filament-forms-section-component md:grid-cols-2">
                    <div
                        class="filament-forms-section-header-wrapper flex rtl:space-x-reverse overflow-hidden rounded-t-xl min-h-[56px] pr-6 pb-4">
                        <div class="flex-1 space-y-1 filament-forms-section-header">
                            <h3 class="flex flex-row items-center text-xl font-bold tracking-tight pointer-events-none">
                                {{ __('general.contributors') }}
                            </h3>

                            <p class="text-base text-gray-500">
                                {{ __('general.provide_information_submission') }}
                            </p>
                        </div>

                    </div>

                    <div class="filament-forms-section-content-wrapper">
                        @error('errors')
                            <div class="flex p-4 mb-4 text-sm border text-danger-800 border-danger-300 rounded-xl bg-danger-50 dark:bg-gray-800 dark:text-danger-400 dark:border-danger-800"
                                role="alert">
                                <svg aria-hidden="true" class="flex-shrink-0 inline w-5 h-5 mr-3" fill="currentColor"
                                    viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span class="sr-only">{{ __('general.info') }}</span>
                                <div>
                                    {{ $message }}
                                </div>
                            </div>
                        @enderror
                        @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList::class, ['submission' => $this->record])
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="flex items-center justify-between">
        <div>
            <x-filament::button icon="heroicon-o-chevron-left" x-show="! isFirstStep()" x-cloak
                x-on:click="previousStep" color="gray" size="sm">
                {{ __('general.previous') }}
            </x-filament::button>
        </div>

        <div>
            {{ $this->nextStep() }}
        </div>
    </div>
</div>
