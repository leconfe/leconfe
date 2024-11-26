<?php

namespace App\Panel\ScheduledConference\Resources;

use App\Actions\Speakers\SpeakerCreateAction;
use App\Actions\Speakers\SpeakerDeleteAction;
use App\Actions\Speakers\SpeakerUpdateAction;
use App\Models\Scopes\ScheduledConferenceScope;
use App\Models\Speaker;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use App\Panel\ScheduledConference\Resources\SpeakerResource\Pages;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SpeakerResource extends Resource
{
    protected static ?string $model = Speaker::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationGroup(): string
    {
        return __('general.conference');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.speakers');
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
        return __('general.speaker');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('speaker_id')
                    ->label(__('general.select_existing_speaker'))
                    ->placeholder(__('general.select_speaker'))
                    ->preload()
                    ->native(false)
                    ->searchable()
                    ->allowHtml()
                    ->optionsLimit(10)
                    ->visible(fn (string $operation) => $operation === 'create')
                    ->getSearchResultsUsing(
                        function (string $search, Select $component) {
                            $speakers = static::getEloquentQuery()->pluck('email')->toArray();

                            return Speaker::query()
                                ->withoutGlobalScope(ScheduledConferenceScope::class)
                                ->whereIn('scheduled_conference_id', app()->getCurrentConference()->scheduledConferences()->pluck('id')->toArray())
                                ->with(['media', 'meta'])
                                ->limit($component->getOptionsLimit())
                                ->whereNotIn('email', $speakers)
                                ->where(fn ($query) => $query->where('given_name', 'LIKE', "%{$search}%")
                                    ->orWhere('family_name', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%"))
                                ->orderBy('created_at', 'desc')
                                ->get()
                                ->unique('email')
                                ->mapWithKeys(fn (Speaker $speaker) => [$speaker->getKey() => static::renderSelectSpeaker($speaker)])
                                ->toArray();
                        }
                    )
                    ->live()
                    ->afterStateUpdated(function (string $state, Select $component, $livewire) {
                        if (! $state) {
                            return;
                        }

                        $form = $component->getContainer();

                        $speaker = Speaker::with(['meta', 'role' => fn ($query) => $query->withoutGlobalScopes(), 'media'])->findOrFail($state);
                        $role = SpeakerRoleResource::getEloquentQuery()->whereName($speaker?->role?->name)->first();

                        $formData = [
                            'speaker_id' => $state,
                            'given_name' => $speaker->given_name,
                            'family_name' => $speaker->family_name,
                            'email' => $speaker->email,
                            'speaker_role_id' => $role->id ?? null,
                            'meta' => $speaker->getAllMeta(),
                        ];

                        if ($speaker->getFirstMedia('profile')) {
                            $livewire->dispatch('update-profile-image', $speaker->getFirstMedia('profile')->getUrl());
                        }

                        return $form->fill($formData);
                    })
                    ->columnSpanFull(),
                ...ContributorForm::generalFormField(app()->getCurrentScheduledConference()),
                Forms\Components\Select::make('speaker_role_id')
                    ->label(__('general.role'))
                    ->required()
                    ->searchable()
                    ->relationship(
                        name: 'role',
                        titleAttribute: 'name',
                    )
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label(__('general.name'))
                            ->required(),
                    ])
                    ->createOptionAction(
                        fn (FormAction $action) => $action->color('primary')
                            ->modalWidth('xl')
                            ->modalHeading(__('general.create_speaker_position'))
                            ->mutateFormDataUsing(function (array $data): array {
                                return $data;
                            })
                            ->form(function (Select $component, Form $form): array|Form|null {
                                return SpeakerRoleResource::form($form);
                            })
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
            ->heading(__('general.speakers_table'))
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-user-plus')
                    ->modalWidth('2xl')
                    ->using(fn (array $data) => SpeakerCreateAction::run($data)),
            ])
            ->columns(ContributorForm::generalTableColumns())
            ->actions(ContributorForm::tableActions(SpeakerUpdateAction::class, SpeakerDeleteAction::class))
            ->filters([
                //
            ]);
    }

    public static function renderSelectSpeaker(Speaker $speaker): string
    {
        return view('forms.select-contributor', ['contributor' => $speaker])->render();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSpeakers::route('/'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            //
        ];
    }
}
