<x-filament::page
    x-on:show-editor-guidance.window="$dispatch('open-modal', { id: 'editor-guidance' })"
    x-data="{
        shouldAutoShowEditorGuidance: {{ auth()->user()->can('actAsEditor', $this->record) && filled(app()->getCurrentScheduledConference()->getMeta('editor_guidelines')) ? 'true' : 'false' }},
        autoShowEditorGuidanceEnabled() {
            return localStorage.getItem('autoShowEditorGuidance') != 0;
        },
        toggleAutoShowEditorGuidance() {
            localStorage.setItem('autoShowEditorGuidance', this.autoShowEditorGuidanceEnabled() ? 0 : 1);
        },
        init() {
            if (this.shouldAutoShowEditorGuidance && this.autoShowEditorGuidanceEnabled()) {
                $nextTick(() => {
                    $dispatch('open-modal', { id: 'editor-guidance' });
                });
            }
        }
    }"
>
    @if ($this->record->stage == App\Models\Enums\SubmissionStage::Wizard)
        @livewire(App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard::class, ['record' => $record])
    @else
        {{ $this->infolist }}
    @endif

    @if (auth()->user()->can('actAsEditor', $this->record) && filled(app()->getCurrentScheduledConference()->getMeta('editor_guidelines')))
        <x-filament::modal id="editor-guidance" width="3xl" :close-by-clicking-away="false">
            <x-slot name="heading">
                {{ __('general.editor_guidance') }}
            </x-slot>

            <div
                class="space-y-4"
                x-data="{
                    autoShowEditorGuidanceEnabled() {
                        return localStorage.getItem('autoShowEditorGuidance') != 0;
                    },
                    toggleAutoShowEditorGuidance() {
                        localStorage.setItem('autoShowEditorGuidance', this.autoShowEditorGuidanceEnabled() ? 0 : 1);
                    }
                }"
            >
                <div class="prose max-w-none dark:prose-invert">
                    {!! app()->getCurrentScheduledConference()->getMeta('editor_guidelines') !!}
                </div>

                <div class="flex items-center">
                    <input id="editor-guidance-checkbox" type="checkbox" value="" class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" :checked="!autoShowEditorGuidanceEnabled()" x-on:change="toggleAutoShowEditorGuidance()">
                    <label for="editor-guidance-checkbox" class="ms-2 text-sm text-gray-900 dark:text-gray-300">
                        Understood editor guidance. Don't show this again
                    </label>
                </div>
            </div>

            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'editor-guidance' })">
                    {{ __('general.close') }}
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
</x-filament::page>
