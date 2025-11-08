<?php

namespace App\Livewire;

use App\Models\Conference;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\ScheduledConference;
use Illuminate\Support\Collection;
use Livewire\Component;

class GlobalNavigation extends Component
{
    public string $search = '';

    public bool $opened = false;

    public function render()
    {
        return view(
            'livewire.global-navigation.index',
            [
                'searchResults' => $this->opened ? $this->getSearchResults() : [],
            ]
        );
    }

    public function open()
    {
        $this->opened = true;
    }

    public function getSearchResults()
    {
        $searchResults = [];

        $scheduledConferences = $this->searchScheduledConferences($this->search);

        if ($scheduledConferences->isNotEmpty()) {
            $searchResults = $scheduledConferences;
        }

        return $searchResults;
    }

    public function searchScheduledConferences(string $search): Collection
    {
        return ScheduledConference::query()
            ->with(['conference'])
            ->withoutGlobalScopes()
            ->whereIn('state', [ScheduledConferenceState::Archived, ScheduledConferenceState::Current])
            ->where('title', 'like', "%$search%")
            ->orderBy('title')
            ->limit(20)
            ->get()
            ->map(fn (ScheduledConference $scheduledConference) => view('livewire.global-navigation.scheduled-conference-search-result', ['scheduledConference' => $scheduledConference])->render());
    }
}
