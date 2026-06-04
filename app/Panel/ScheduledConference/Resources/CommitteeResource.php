<?php

namespace App\Panel\ScheduledConference\Resources;

use App\Actions\Committees\CommitteeCreateAction;
use App\Actions\Committees\CommitteeDeleteAction;
use App\Actions\Committees\CommitteeUpdateAction;
use App\Models\Committee;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use App\Panel\ScheduledConference\Resources\CommitteeResource\Pages;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommitteeResource extends Resource
{
    protected static ?string $model = Committee::class;

    public static function getNavigationGroup(): string
    {
        return __('general.conference');
    }

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('general.committee');
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()
            ->orderBy('order_column')
            ->with([
                'role',
                'media',
                'meta',
            ])
            ->whereHas('role');
    }

    public static function getModelLabel(): string
    {
        return __('general.committee');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                ...ContributorForm::generalFormField(app()->getCurrentScheduledConference()),
                Forms\Components\Select::make('committee_role_id')
                    ->label(__('general.role'))
                    ->required()
                    ->searchable()
                    ->relationship(
                        name: 'role',
                        titleAttribute: 'name',
                    )
                    ->preload()
                    ->createOptionForm(fn ($form) => CommitteeRoleResource::form($form))
                    ->createOptionAction(
                        fn (FormAction $action) => $action->color('primary')
                            ->modalWidth('xl')
                            ->modalHeading(__('general.create_committee_role'))
                    )
                    ->columnSpan([
                        'lg' => 2,
                    ]),
                ...ContributorForm::additionalFormField(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order_column')
            ->heading(__('general.committee_table'))
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-user-plus')
                    ->modalWidth('2xl')
                    ->using(fn (array $data) => CommitteeCreateAction::run($data)),
            ])
            ->columns(ContributorForm::generalTableColumns())
            ->actions(ContributorForm::tableActions(CommitteeUpdateAction::class, CommitteeDeleteAction::class))
            ->filters([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCommittee::route('/'),
        ];
    }
}
