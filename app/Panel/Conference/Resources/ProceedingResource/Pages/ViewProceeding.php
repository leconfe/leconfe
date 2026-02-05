<?php

namespace App\Panel\Conference\Resources\ProceedingResource\Pages;

use App\Actions\Proceedings\ProceedingUpdateAction;
use App\Models\Proceeding;
use App\Models\Submission;
use App\Panel\Conference\Resources\ProceedingResource;
use App\Tables\Columns\IndexColumn;
use Filament\Actions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ViewProceeding extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = ProceedingResource::class;

    protected static string $view = 'panel.conference.resources.proceeding-resource.pages.view-proceeding';

    public ?array $data = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
        $this->form->fill([
            ...$this->record->attributesToArray(),
            'meta' => $this->record->getAllMeta()->toArray(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label(__('general.preview'))
                ->icon('heroicon-o-eye')
                ->hidden(fn (Proceeding $record) => ! $record->published)
                ->url(fn (Proceeding $record) => route('livewirePageGroup.conference.pages.proceeding-detail', [$record->id]), true),
        ];
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function can(string $action, ?Model $record = null)
    {
        return static::getResource()::can($action, $record);
    }

    public function getBreadcrumb(): string
    {
        return __('filament-panels::resources/pages/view-record.breadcrumb');
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    public function form(Form $form): Form
    {
        $form
            ->disabled(fn () => ! $this->can('update', $this->record))
            ->model($this->record);

        return static::getResource()::form($form)
            ->statePath('data');
    }

    public function submit()
    {
        abort_unless($this->can('update', $this->record), 403);

        $data = $this->form->getState();
        ProceedingUpdateAction::run($this->record, $data);

        $this->form->saveRelationships();

         Notification::make()
                ->success()
                ->title(__('general.saved'))
                ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Submission::query()
                    ->where('proceeding_id', $this->record->id)
                    ->with(['scheduledConference', 'meta'])
                    ->ordered()
            )
            ->defaultGroup('track.title')
            ->groupingSettingsHidden()
            ->groups([
                'track.title',
            ])
            ->reorderable('proceeding_order_column')
            ->columns([
                IndexColumn::make('no')
                    ->label('No.'),
                TextColumn::make('title')
                    ->wrap()
                    ->label(__('general.title'))
                    ->getStateUsing(fn (Submission $record) => $record->getMeta('title'))
                    ->url(fn (Submission $record) => route('filament.scheduledConference.resources.submissions.view', ['record' => $record->id, 'serie' => $record->scheduledConference]))
                    ->searchable()
                    ->color('primary'),
                TextColumn::make('pages')
                    ->getStateUsing(fn (Submission $record) => $record->getMeta('article_pages')),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                Action::make('remove')
                    ->label(__('general.remove'))
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(fn (Submission $record) => $record->unassignProceeding()),
            ])
            ->bulkActions([
                // ...
            ]);
    }
}
