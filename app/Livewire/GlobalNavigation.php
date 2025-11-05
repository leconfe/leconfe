<?php

namespace App\Livewire;

use App\Models\Conference;
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

        $conferences = $this->searchConferences($this->search);
        $scheduledConferences = $this->searchScheduledConferences($this->search);

        if ($conferences->isNotEmpty()) {
            $searchResults['Conferences'] = $conferences;
        }

        if ($scheduledConferences->isNotEmpty()) {
            $searchResults['Scheduled Conferences'] = $scheduledConferences;
        }

        return $searchResults;
    }

    public function searchConferences(string $search): Collection
    {
        return Conference::query()
            ->withoutGlobalScopes()
            ->where('name', 'like', "%$search%")
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Conference $conference) => view('livewire.global-navigation.conference-search-result', ['conference' => $conference])->render());
    }

    public function searchScheduledConferences(string $search): Collection
    {
        return ScheduledConference::query()
            ->with(['conference'])
            ->withoutGlobalScopes()
            ->published()
            ->where('title', 'like', "%$search%")
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->map(fn (ScheduledConference $scheduledConference) => view('livewire.global-navigation.scheduled-conference-search-result', ['scheduledConference' => $scheduledConference])->render());
    }
}
