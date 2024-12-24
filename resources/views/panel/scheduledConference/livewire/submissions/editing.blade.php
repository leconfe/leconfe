<div class="space-y-6">
    <div @class([
        'gap-4',
        'grid grid-cols-12' => auth()->user()->can('actAsEditor', $submission),
    ])>
        <div class="space-y-4 col-span-8">
            {{-- Draft Files --}}
            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\DraftFiles::class, ['submission' => $submission])

            {{-- Edited Files --}}
            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\ProductionFiles::class, ['submission' => $submission])
        </div>
        @can('actAsEditor', $submission)
        <div class="space-y-4 col-span-4">

            {{-- Participants --}}
            @livewire(App\Panel\ScheduledConference\Livewire\Submissions\Components\ParticipantList::class, ['submission' => $submission])
            
        </div>
        @endcan
    </div>
</div>
