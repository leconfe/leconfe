<?php

namespace App\Panel\Conference\Pages;

use App\Models\Metric;
use App\Models\Proceeding;
use App\Models\Submission;
use App\Models\SubmissionGalley;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Reactive;

class PaperStatistic extends Page implements HasForms, HasInfolists, HasActions, HasTable
{
    use InteractsWithForms, InteractsWithInfolists, InteractsWithActions, InteractsWithTable;

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'panel.conference.pages.paper-statistic';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('general.paper_statistic_page_navigation');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.paper_statistic_page_heading');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.statistics');
    }

    public function mount(): void
    {
        $this->authorize('update', App::getCurrentConference());

        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Submission::query()
                    ->with(['galleys', 'meta'])
                    ->when(data_get($this->data, 'proceeding_ids'), fn($query) => $query->whereIn('proceeding_id', data_get($this->data, 'proceeding_ids')))
                    ->withSum(['metrics as abstract_view' => fn($query) => $query->whereBetween('log_at', [data_get($this->data, 'date_start'), data_get($this->data, 'date_end')])], 'metric')
                    ->whereHas('metrics', fn($query) => $query->whereBetween('log_at', [data_get($this->data, 'date_start'), data_get($this->data, 'date_end')]))
            )
            ->columns([
                TextColumn::make('title')
                    ->label(__('general.title'))
                    ->getStateUsing(fn(Submission $record) => $record->getMeta('title'))
                    ->wrap()
                    ->size(TextColumnSize::ExtraSmall),
                TextColumn::make('abstract_view')
                    ->label(__('general.abstract_view')),
                TextColumn::make('galley_view')
                    ->label(__('general.galley_view'))
                    ->getStateUsing(
                        fn(Submission $record) => Metric::query()
                            ->where('model_type', SubmissionGalley::class)
                            ->whereIn('model_id', $record->galleys->pluck('id')->toArray())
                            ->whereBetween('log_at', [data_get($this->data, 'date_start'), data_get($this->data, 'date_end')])
                            ->sum('metric')
                    )
            ])
            ->filters([
                // ...
            ])
            ->actions([
                // ...
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Actions::make([
                    Action::make('lastThirtyDay')
                        ->label('Last 30 days')
                        ->action(function (Set $set) {
                            $set('date_start', now()->subMonth()->format('Y-m-d'));
                            $set('date_end', now()->subDay()->format('Y-m-d'));

                            $this->updateStatistic();
                        }),
                    Action::make('lastNinetyDay')
                        ->label('Last 12 months')
                        ->action(function (Set $set) {
                            $set('date_start', now()->subYear()->format('Y-m-d'));
                            $set('date_end', now()->subDay()->format('Y-m-d'));

                            $this->updateStatistic();
                        }),
                ])
                    ->grow()
                    ->fullWidth(),
                Select::make('proceeding_ids')
                    ->label(__('general.proceedings'))
                    ->options(Proceeding::pluck('title', 'id'))
                    ->multiple()
                    ->native(false)
                    ->reactive()
                    ->afterStateUpdated(fn() => $this->updateStatistic()),
                Fieldset::make('Custom Range')
                    ->columns([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->schema([
                        DatePicker::make('date_start')
                            ->label('')
                            ->default(now()->subMonth()->startOfDay())
                            ->maxDate(now()->subDay())
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->updateStatistic()),
                        DatePicker::make('date_end')
                            ->label('')
                            ->default(now()->subday()->endOfDay())
                            ->maxDate(now()->subDay()->endOfDay())
                            ->native(false)
                            ->reactive()
                            ->afterStateUpdated(fn() => $this->updateStatistic()),
                    ]),
            ])
            ->statePath('data');
    }

    public function updateStatistic()
    {
        $this->resetTable();
        $this->dispatch('statistic-updated', $this->data);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('update', App::getCurrentConference());
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // ArticleStatisticChart::class
        ];
    }
}
