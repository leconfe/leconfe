<div class="space-y-6">
    <div class="p-6 bg-white border border-gray-200 filament-forms-card-component dark:bg-gray-900 rounded-xl dark:border-gray-800">
        <div class="grid grid-cols-1 gap-6 filament-forms-component-container">
            <div class="col-span-full">
                <div id="upload-files" class="grid grid-cols-1 filament-forms-section-component md:grid-cols-2">
                    <div
                        class="filament-forms-section-header-wrapper flex rtl:space-x-reverse overflow-hidden rounded-t-xl min-h-[56px] pr-6 pb-4">
                        <div class="flex-1 space-y-1 filament-forms-section-header">
                            <h3 class="flex flex-row items-center text-xl font-bold tracking-tight pointer-events-none">
                                {{ __('general.review_and_submit') }}
                            </h3>
                            <div class="space-y-4">
                                <p class="text-base text-gray-500">
                                    {{ __('general.before_submitting') }}
                                </p>
                                <p class="text-base text-gray-500">
                                    {{ __('general.after_submitting') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 filament-forms-section-content-wrapper">
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
                        <div class="space-y-4">
                            <div class="flex ml-auto">
                                <x-filament::button class="ml-auto" :outlined="true" size="sm" x-on:click="step = 'detail'">
                                    {{ __('general.edit_submission') }}
                                </x-filament::button>
                            </div>
                            <ul
                                class="w-full text-sm text-gray-900 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-800 dark:text-white">
                                <li
                                    class="flex items-center justify-between w-full p-4 border-b border-gray-200 rounded-t-lg sm:px-6 dark:border-gray-800">
                                    <div class="text-base font-bold">{{ __('general.details') }}</div>
                                </li>
                                <li class="w-full p-4 border-b border-gray-200 sm:px-6 dark:border-gray-800">
                                    <div class="font-medium">{{ __('general.title') }}</div>
                                    <div class="text-gray-500">{{ $this->record->getLocalizedMeta('title') }}</div>
                                </li>
                                <li class="w-full p-4 space-y-2 border-b border-gray-200 sm:px-6 dark:border-gray-800">
                                    <div class="font-medium">{{ __('general.keywords') }}</div>
                                    <div class="flex flex-wrap items-center gap-1 text-gray-500">
                                        @forelse ($this->record->getMeta('keywords') ?? [] as $keyword)
                                            <span @class([
                                                'inline-flex items-center justify-center min-h-6 px-2 py-0.5 text-sm tracking-tight rounded-xl text-primary-700 bg-primary-500/10 whitespace-normal',
                                                'dark:text-primary-500' => config('tables.dark_mode'),
                                            ])>
                                                {{ $keyword }}
                                            </span>
                                        @empty
                                            {{ __('general.no_information_been_provide') }}
                                        @endforelse
                                    </div>
                                </li>
                                <li class="w-full p-4 border-gray-800 sm:px-6 dark:border-gray-800">
                                    <div class="font-medium">{{ __('general.abstract') }}</div>
                                    <div class="text-gray-500">
                                        {!! $this->record->getMeta('abstract') !!}
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="space-y-4">
                            <div class="flex ml-auto">
                                <x-filament::button class="ml-auto" :outlined="true" size="sm" x-on:click="step = 'upload-files'">
                                    {{ __('general.edit_files') }}
                                </x-filament::button>
                            </div>
                            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\AbstractFiles::class, ['submission' => $record, 'viewOnly' => true])
                        </div>
                        <div class="space-y-4">
                            <div class="flex ml-auto">
                                <x-filament::button class="ml-auto" :outlined="true" size="sm"  x-on:click="step = 'authors'">
                                    {{ __('general.edit_contributors') }}
                                </x-filament::button>
                            </div>
                            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList::class, ['submission' => $record, 'viewOnly' => true])
                        </div>
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
            {{ $this->submitAction }}
            <x-filament-actions::modals />
        </div>
    </div>

    {{-- <x-filament::modal id="modalSubmisionWizardConfirmation">
        <x-slot name="header">
            <x-filament::modal.heading>
                Submit
            </x-filament::modal.heading>
        </x-slot>
        You are about to submit the <b>{{ $this->record->getMeta('title') }}</b> to
        <b>{{ setting('conference.title') }}</b> for editorial
        review. Are you sure you want
        to proceed with this submission?
        <x-slot name="footer" class="test">
            <div
                class="flex flex-row-reverse flex-wrap items-center gap-4 space-x-reverse filament-modal-actions rtl:space-x-reverse">
                <x-filament::button x-cloak wire:loading.class.delay="opacity-70 cursor-wait" size="sm"
                    wire:click="submit">
                    Yes, Submit
                </x-filament::button>
                {{ $this->submitAction }}
                <x-filament::button x-cloak color="secondary" :outlined="true"
                    x-on:click="$dispatch('close-modal', { id: 'modalSubmisionWizardConfirmation' })"
                    wire:loading.class.delay="opacity-70 cursor-wait" size="sm">
                    Cancel
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal> --}}
</div>
