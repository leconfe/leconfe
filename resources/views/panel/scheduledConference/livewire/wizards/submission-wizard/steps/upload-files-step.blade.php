<div class="space-y-6">
    <div class="p-6 bg-white border border-gray-200 filament-forms-card-component dark:bg-gray-900 rounded-xl dark:border-gray-800">
        <div class="grid grid-cols-1 gap-6 filament-forms-component-container">
            <div class="col-span-full">
                <div id="upload-files" class="grid grid-cols-1 filament-forms-section-component md:grid-cols-2">
                    <div
                        class="filament-forms-section-header-wrapper flex rtl:space-x-reverse overflow-hidden min-h-[56px] pr-6 pb-4">
                        <div class="flex-1 space-y-4 filament-forms-section-header">
                            <h3 class="flex flex-row items-center text-xl font-bold tracking-tight pointer-events-none">
                                {{ __('general.upload_files') }}
                            </h3>

                            <p class="text-base text-gray-500">
                                {{ __('general.include_any_necessary_files') }}
                            </p>

                            @php($requiredUploadTypeStatuses = $this->requiredUploadTypeStatuses())

                            @if ($requiredUploadTypeStatuses->isNotEmpty())
                                <div
                                    class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-800 dark:bg-gray-950">
                                    <div class="space-y-1">
                                        <h4 class="font-semibold text-gray-950 dark:text-white">
                                            {{ __('general.required_files') }}
                                        </h4>
                                        <p class="text-gray-600 dark:text-gray-400">
                                            {{ __('general.required_files_helper') }}
                                        </p>
                                    </div>

                                    <ul class="space-y-2">
                                        @foreach ($requiredUploadTypeStatuses as $requiredUploadType)
                                            <li class="flex items-start justify-between gap-3">
                                                <span class="font-medium text-gray-800 dark:text-gray-200">
                                                    {{ $requiredUploadType['name'] }}
                                                </span>
                                                <span
                                                    @class([
                                                        'inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium',
                                                        'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $requiredUploadType['uploaded'],
                                                        'bg-danger-600 text-white dark:bg-danger-500 dark:text-white' => ! $requiredUploadType['uploaded'],
                                                    ])>
                                                    @if ($requiredUploadType['uploaded'])
                                                        <x-heroicon-m-check-circle class="h-4 w-4" />
                                                        {{ __('general.uploaded') }}
                                                    @else
                                                        <x-heroicon-m-x-circle class="required-upload-missing-icon h-4 w-4 text-white" />
                                                        {{ __('general.not_uploaded') }}
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                        @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\AbstractFiles::class, ['submission' => $record])
                </div>
            </div>
        </div>
    </div>
    <div class="flex items-center justify-between">
        <div>
            <x-filament::button icon="heroicon-o-chevron-left" x-show="! isFirstStep()" x-cloak x-on:click="previousStep"
                color="gray" size="sm">
              {{__('general.previous')}}
            </x-filament::button>
        </div>
        <div>
            {{ $this->nextStep() }}
        </div>
    </div>
</div>
