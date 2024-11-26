<?php

namespace App\Panel\ScheduledConference\Resources;

use App\Actions\Committees\CommitteeCreateAction;
use App\Actions\Committees\CommitteeDeleteAction;
use App\Actions\Committees\CommitteeUpdateAction;
use App\Models\Committee;
use App\Models\Scopes\ScheduledConferenceScope;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use App\Panel\ScheduledConference\Resources\CommitteeResource\Pages;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Select;
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
                Select::make('committee_id')
                    ->label(__('general.select_existing_committee'))
                    ->placeholder(__('general.select_committee'))
                    ->preload()
                    ->native(false)
                    ->searchable()
                    ->allowHtml()
                    ->optionsLimit(10)
                    ->visible(fn (string $operation) => $operation === 'create')
                    ->getSearchResultsUsing(
                        function (string $search, Select $component) {
                            $committees = static::getEloquentQuery()->pluck('email')->toArray();

                            return Committee::query()
                                ->withoutGlobalScope(ScheduledConferenceScope::class)
                                ->whereIn('scheduled_conference_id', app()->getCurrentConference()->scheduledConferences()->pluck('id')->toArray())
                                ->with(['media', 'meta'])
                                ->limit($component->getOptionsLimit())
                                ->whereNotIn('email', $committees)
                                ->where(fn ($query) => $query->where('given_name', 'LIKE', "%{$search}%")
                                    ->orWhere('family_name', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%"))
                                ->orderBy('created_at', 'desc')
                                ->get()
                                ->unique('email')
                                ->mapWithKeys(fn (Committee $committee) => [$committee->getKey() => static::renderSelectCommittee($committee)])
                                ->toArray();
                        }
                    )
                    ->live()
                    ->afterStateUpdated(function (string $state, Select $component, $livewire) {
                        if (! $state) {
                            return;
                        }

                        $form = $component->getContainer();

                        $committee = Committee::query()
                            ->withoutGlobalScope(ScheduledConferenceScope::class)
                            ->with(['meta', 'role' => fn ($query) => $query->withoutGlobalScopes(), 'media'])->findOrFail($state);

                        $role = CommitteeRoleResource::getEloquentQuery()->whereName($committee?->role?->name)->first();

                        $formData = [
                            'committee_id' => $state,
                            'given_name' => $committee->given_name,
                            'family_name' => $committee->family_name,
                            'email' => $committee->email,
                            'committee_role_id' => $role->id ?? null,
                            'meta' => $committee->getAllMeta(),
                        ];

                        if ($committee->getFirstMedia('profile')) {
                            $livewire->dispatch('update-profile-image', $committee->getFirstMedia('profile')->getUrl());
                        }

                        return $form->fill($formData);
                    })
                    ->columnSpanFull(),
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

    public static function renderSelectCommittee(Committee $committee): string
    {
        return view('forms.select-contributor', ['contributor' => $committee])->render();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCommittee::route('/'),
        ];
    }
}
