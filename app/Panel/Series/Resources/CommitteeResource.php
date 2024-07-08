<?php

namespace App\Panel\Series\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use App\Models\Committee;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;
use App\Actions\Committees\CommitteeCreateAction;
use App\Actions\Committees\CommitteeDeleteAction;
use App\Actions\Committees\CommitteeUpdateAction;
use App\Models\Scopes\ConferenceScope;
use Filament\Forms\Components\Actions\Action as FormAction;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use App\Panel\Series\Resources\CommitteeResource\Pages;

class CommitteeResource extends Resource
{
    // protected static ?string $navigationGroup = 'Conferences';

    protected static ?string $model = Committee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('translation.committeResource.getModelLabelCommittee');
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
        return __('translation.committeResource.getModelLabelCommittee');
    }

    public static function selectCommitteeField($form): Select
    {
        return Select::make('committee_id')
            ->label(__('translation.committeResource.committeResourceLabelSelectExisting'))
            ->placeholder(__('translation.committeResource.committeResourcePlaceHolderSelectExisting'))
            ->preload()
            ->native(false)
            ->searchable()
            ->allowHtml()
            ->optionsLimit(10)
            ->getSearchResultsUsing(
                function (string $search, Select $component) {
                    $committees = static::getEloquentQuery()->pluck('email')->toArray();

                    return Committee::query()
                        ->with(['media', 'meta'])
                        ->limit($component->getOptionsLimit())
                        ->whereNotIn('email', $committees)
                        ->where(fn ($query) => $query->where('given_name', 'LIKE', "%{$search}%")
                            ->orWhere('family_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%"))
                        ->get()
                        ->mapWithKeys(fn (Committee $committee) => [$committee->getKey() => static::renderSelectCommittee($committee)])
                        ->toArray();
                }
            )
            ->live()
            ->afterStateUpdated(function ($state) use ($form) {
                if (! $state) {
                    return;
                }
                $committee = Committee::with(['meta', 'role' => fn ($query) => $query->withoutGlobalScopes()])->findOrFail($state);
                $role = CommitteeRoleResource::getEloquentQuery()->whereName($committee?->role?->name)->first();

                $formData = [ 
                    'committee_id' => $state,
                    'given_name' => $committee->given_name,
                    'family_name' => $committee->family_name,
                    'email' => $committee->email,
                    'committee_role_id' => $role->id ?? null,
                    'meta' => $committee->getAllMeta()
                ];
                return static::form($form)->fill($formData);
            })
            ->columnSpanFull();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                static::selectCommitteeField($form),
                ...ContributorForm::generalFormField(app()->getCurrentSerie()),
                Forms\Components\Select::make('committee_role_id')
                    ->label(__('translation.committeResource.committeResourceLabelRole'))
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
                            ->modalHeading(__('translation.committeResource.committeResourcemodalHeadingRole'))
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
            ->heading(__('translation.committeResource.committeResourceHeadingCommitteeTable'))
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-user-plus')
                    ->modalWidth('2xl')
                    ->using(fn (array $data) => CommitteeCreateAction::run($data)),
            ])
            ->columns([
                ...ContributorForm::generalTableColumns(),
            ])
            ->actions([
                ...ContributorForm::tableActions(CommitteeUpdateAction::class, CommitteeDeleteAction::class),
            ])
            ->filters([]);
    }

    public static function renderSelectCommittee(Committee $committee): string
    {
        $committee->load(['serie' => fn ($query) => $query->withoutGlobalScopes([ConferenceScope::class])]);
        return view('forms.select-contributor-serie', ['contributor' => $committee])->render();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCommittee::route('/'),
        ];
    }
}
