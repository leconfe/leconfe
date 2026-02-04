<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Presentation;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\Track;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Livewire\WithPagination;

class Presentations extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string $view = 'panel.scheduledConference.pages.presentations';

    protected static ?int $navigationSort = 99;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([]);  
    }

    public function updatedFormData(): void
    {
        $this->resetPage();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns(4)
                    ->schema([
                        TextInput::make('search')
                            ->label('Search Title')
                            ->placeholder('Search by title')
                            ->suffixIcon('heroicon-m-magnifying-glass')
                            ->debounce(),
                        Select::make('track_id')
                            ->label('Track')
                            ->placeholder('All tracks')
                            ->searchable()
                            ->options(fn () => Track::query()
                                ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
                                ->pluck('title', 'id')),
                        Select::make('topic_id')
                            ->label('Topic')
                            ->placeholder('All topics')
                            ->searchable()
                            ->options(fn () => Topic::query()
                                ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
                                ->pluck('name', 'id')),
                        TextInput::make('keyword')
                            ->label('Keyword')
                            ->placeholder('Search by keyword')
                            ->suffixIcon('heroicon-m-tag')
                            ->debounce(),
                    ]),
            ])
            ->statePath('formData');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $search = trim((string) data_get($this->formData, 'search'));
        $trackId = data_get($this->formData, 'track_id');
        $topicId = data_get($this->formData, 'topic_id');
        $keyword = trim((string) data_get($this->formData, 'keyword'));

        $presentations = Presentation::query()
            ->with([
                'meta',
                'media',
                'submission' => ['meta', 'track', 'authors'],
            ])
            ->isFinal()
            ->orderBy(
                Submission::query()
                    ->select('created_at')
                    ->whereColumn('submissions.id', 'presentations.submission_id')
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('submission.meta', function ($metaQuery) use ($search) {
                    $metaQuery
                        ->where('key', 'title')
                        ->where('value', 'like', '%' . $search . '%');
                });
            })
            ->when($trackId, function ($query) use ($trackId) {
                $query->whereHas('submission', function ($submissionQuery) use ($trackId) {
                    $submissionQuery->where('track_id', $trackId);
                });
            })
            ->when($topicId, function ($query) use ($topicId) {
                $query->whereHas('submission.topics', function ($topicsQuery) use ($topicId) {
                    $topicsQuery->whereKey($topicId);
                });
            })
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->whereHas('submission.meta', function ($metaQuery) use ($keyword) {
                    $metaQuery
                        ->where('key', 'keywords')
                        ->where('value', 'like', '%' . $keyword . '%');
                });
            })
            ->paginate(6);

        return [
           'presentations' => $presentations
        ];
    }
}
