<div>
    <x-filament::section heading="{{ __('translation.submissions.submissionResourceSubmissionDetail') }}">
        <form wire:submit='submit'>
            <div class="space-y-4">
                {{ $this->form }}
                @can('editing', $submission)
                    <x-filament::button type="submit" icon="iconpark-save-o">
                       {{ __('translation.button.save') }}
                    </x-filament::button>
                @endcan
            </div>
        </form>
    </x-filament::section>
</div>
