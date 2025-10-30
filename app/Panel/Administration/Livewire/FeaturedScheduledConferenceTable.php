<?php

namespace App\Panel\Administration\Livewire;

use App\Actions\Stakeholders\StakeholderCreateAction;
use App\Actions\Stakeholders\StakeholderUpdateAction;
use App\Facades\Setting;
use App\Models\ScheduledConference;
use App\Models\Scopes\ConferenceScope;
use App\Models\Scopes\ScheduledConferenceScope;
use App\Models\Stakeholder;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\CheckboxList;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class FeaturedScheduledConferenceTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function getEloquentQuery()
    {
        return ScheduledConference::query()
            ->withoutGlobalScopes([
                ConferenceScope::class,
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getEloquentQuery()->whereNotNull('featured'))
            ->heading('Featured Scheduled Conferences')
            ->reorderable('featured')
            ->defaultSort('featured', 'asc')
            ->columns([
                IndexColumn::make('no.'),
                TextColumn::make('title')
                    ->label(__('general.title'))
                    ->searchable()
                    ->wrap()
                    ->wrapHeader(),
                TextColumn::make('date_start')
                    ->label(__('general.start_date'))
                    ->date(Setting::get('format_date')),
                TextColumn::make('date_end')
                    ->label(__('general.end_date'))
                    ->date(Setting::get('format_date')),
            ])
            ->headerActions([
                Action::make('add_featured')
                    ->fillForm([])
                    ->form([
                        CheckboxList::make('featured_scheduled_conferences')
                            ->hiddenLabel()
                            ->searchable()
                            ->options(fn() => $this->getEloquentQuery()->whereNull('featured')->orderBy('date_start', 'desc')->pluck('title', 'id'))
                    ])
                    ->action(function (array $data) {
                        // Get latest number of featured conferences
                        $latestFeatured = $this->getEloquentQuery()->whereNotNull('featured')->count();
                        // Update the featured column for each selected conference
                        foreach ($data['featured_scheduled_conferences'] as $id) {
                            $this->getEloquentQuery()->find($id)->update(['featured' => $latestFeatured + 1]);
                            $latestFeatured++;
                        }
                    })
            ])
            ->filters([
                // ...
            ])
            ->actions([
                Action::make('remove')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash')
                    ->action(function (ScheduledConference $record) {
                        $record->update(['featured' => null]);
                    })
                    ->requiresConfirmation()
                    ->color('danger'),
            ])
            ->bulkActions([
                BulkAction::make('remove_featured')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-trash')
                    ->action(function (Collection $records) {
                        if ($records->isEmpty()) {
                            return;
                        }
                        $records->toQuery()->update(['featured' => null]);
                    })
                    ->requiresConfirmation()
                    ->color('danger'),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                SpatieMediaLibraryFileUpload::make('logo')
                    ->label(__('general.logo'))
                    ->image()
                    ->key('logo')
                    ->collection('logo')
                    ->alignCenter()
                    ->imageResizeUpscale(false),
                TextInput::make('name')
                    ->label(__('general.name'))
                    ->required(),
                TextInput::make('meta.url')
                    ->label(__('general.url'))
                    ->url()
                    ->validationMessages([
                        'url' => __('general.url_must_be_valid'),
                    ]),
            ]);
    }
}
