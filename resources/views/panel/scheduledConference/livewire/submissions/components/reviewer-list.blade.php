<div>
    @if($this->reviewRounds->isNotEmpty() || auth()->user()?->can('assignReviewer', $record))
        <x-filament::tabs :contained="true" class="!mx-0 border-b">
            @foreach($this->reviewRounds as $round)
                <x-filament::tabs.item
                    :active="$this->selectedRoundId === $round->getKey()"
                    wire:click="selectRound({{ $round->getKey() }})"
                >
                    <span class="inline-flex items-center gap-2">
                        <span>Round {{ $round->round_number }}</span>
                        <x-filament::badge
                            size="sm"
                            :color="$round->isOpen() ? 'success' : 'gray'"
                        >
                            {{ $round->status }}
                        </x-filament::badge>
                    </span>
                </x-filament::tabs.item>
            @endforeach

            @can('assignReviewer', $record)
                <x-filament::tabs.item
                    wire:click="mountAction('newReviewRoundAction')"
                    icon="heroicon-o-plus"
                >
                    New Review Round
                </x-filament::tabs.item>
            @endcan
        </x-filament::tabs>
    @endif

    <div class="p-4">
        {{ $this->table }}
    </div>
    <x-filament-actions::modals />
</div>
