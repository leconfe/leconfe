<?php

namespace App\Panel\Series\Resources;

use Filament\Forms;
use App\Models\Speaker;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;
use App\Actions\Speakers\SpeakerCreateAction;
use App\Actions\Speakers\SpeakerDeleteAction;
use App\Actions\Speakers\SpeakerUpdateAction;
use App\Models\Scopes\ConferenceScope;
use Filament\Forms\Components\Actions\Action as FormAction;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use App\Panel\Series\Resources\SpeakerResource\Pages;

class SpeakerResource extends Resource
{
    // protected static ?string $navigationGroup = 'Conferences';

    protected static ?string $model = Speaker::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('translation.seriesWidget.speakerWidgetHeading');
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
        return __('translation.seriesWidget.speakerWidgetHeading');
    }

    public static function selectSpeakerField($form): Select
    {
        return Select::make('speaker_id')
            ->label(__('translation.speakerResource.speakerResourceLabelSelectSpeaker'))
            ->placeholder(__('translation.speakerResource.speakerResourceplaceholderSelectSpeaker'))
            ->preload()
            ->native(false)
            ->searchable()
            ->allowHtml()
            ->optionsLimit(10)
            ->getSearchResultsUsing(
                function (string $search, Select $component) {
                    $speakers = static::getEloquentQuery()->pluck('email')->toArray();

                    return Speaker::query()
                        ->with(['media', 'meta'])
                        ->limit($component->getOptionsLimit())
                        ->whereNotIn('email', $speakers)
                        ->where(fn ($query) => $query->where('given_name', 'LIKE', "%{$search}%")
                            ->orWhere('family_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%"))
                        ->get()
                        ->mapWithKeys(fn (Speaker $speaker) => [$speaker->getKey() => static::renderSelectSpeaker($speaker)])
                        ->toArray();
                }
            )
            ->live()
            ->afterStateUpdated(function ($state) use ($form) {
                if (! $state) {
                    return;
                }
                $speaker = Speaker::with(['meta', 'role' => fn ($query) => $query->withoutGlobalScopes()])->findOrFail($state);
                $role = SpeakerRoleResource::getEloquentQuery()->whereName($speaker?->role?->name)->first();

                $formData = [ 
                    'speaker_id' => $state,
                    'given_name' => $speaker->given_name,
                    'family_name' => $speaker->family_name,
                    'email' => $speaker->email,
                    'speaker_role_id' => $role->id ?? null,
                    'meta' => $speaker->getAllMeta()
                ];
                return static::form($form)->fill($formData);
            })
            ->columnSpanFull();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                static::selectSpeakerField($form),
                ...ContributorForm::generalFormField(app()->getCurrentSerie()),
                Forms\Components\Select::make('speaker_role_id')
                    ->label(__('translation.speakerResource.speakerResourceLabelRole'))
                    ->required()
                    ->searchable()
                    ->relationship(
                        name: 'role',
                        titleAttribute: 'name',
                    )
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label(__('translation.speakerResource.speakerResourceLabelName'))
                            ->required(),
                    ])
                    ->createOptionAction(
                        fn (FormAction $action) => $action->color('primary')
                            ->modalWidth('xl')
                            ->modalHeading(__('translation.speakerResource.speakerResourcemodalHeadingCreateSpeakerPosition'))
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
            ->heading(__('translation.speakerResource.speakerResourceHeadingSpeakersTable'))
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-user-plus')
                    ->modalWidth('2xl')
                    ->using(fn (array $data) => SpeakerCreateAction::run($data)),
            ])
            ->columns([
                ...ContributorForm::generalTableColumns(),
            ])
            ->actions([
                ...ContributorForm::tableActions(SpeakerUpdateAction::class, SpeakerDeleteAction::class),
            ])
            ->filters([
                //
            ]);
    }

    public static function renderSelectSpeaker(Speaker $speaker): string
    {
        $speaker->load(['serie' => fn ($query) => $query->withoutGlobalScopes([ConferenceScope::class])]);
        return view('forms.select-contributor-serie', ['contributor' => $speaker])->render();
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
