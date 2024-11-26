<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Tables\Columns\IndexColumn;
use Filament\Forms;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;
use Squire\Models\Country;

class ContributorForm extends Component
{
    public static function generalFormField(Model $modelType): array
    {
        return [
            Forms\Components\SpatieMediaLibraryFileUpload::make('profile')
                ->label(__('general.profile_picture'))
                ->image()
                ->key('profile')
                ->collection('profile')
                ->conversion('thumb')
                ->alignCenter()
                ->columnSpan([
                    'lg' => 2,
                ])
                ->extraAlpineAttributes([
                    'x-on:update-profile-image.window' => 'setTimeout(() => { pond.removeFiles({ revert: false }); pond.addFile($event.detail);}, 750);',
                ]),
            Forms\Components\TextInput::make('given_name')
                ->label(__('general.given_name'))
                ->required(),
            Forms\Components\TextInput::make('family_name')
                ->label(__('general.family_name')),
            Forms\Components\TextInput::make('email')
                ->label(__('general.email'))
                ->required()
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: function (Unique $rule) use ($modelType) {
                        return $rule
                            ->when($modelType instanceof Conference, fn ($rule) => $rule->where('conference_id', $modelType->getKey()))
                            ->when($modelType instanceof ScheduledConference, fn ($rule) => $rule->where('scheduled_conference_id', $modelType->getKey()))
                            ->when($modelType instanceof Submission, fn ($rule) => $rule->where('submission_id', $modelType->getKey()));
                    }
                )
                ->columnSpan([
                    'lg' => 2,
                ]),
            Forms\Components\TextInput::make('meta.public_name')
                ->label(__('general.public_name'))
                ->helperText(__('general.public_name_helper'))
                ->columnSpan(['lg' => 2]),
        ];
    }

    public static function additionalFormField(): array
    {
        return [
            Forms\Components\TagsInput::make('meta.expertise')
                ->label(__('general.expertise'))
                ->placeholder('')
                ->columnSpan([
                    'lg' => 2,
                ]),
            Forms\Components\TextInput::make('meta.affiliation')
                ->label(__('general.affiliation'))
                ->prefixIcon('heroicon-s-building-library')
                ->placeholder('University of Jakarta')
                ->columnSpan([
                    'lg' => 2,
                ]),
            Forms\Components\Select::make('meta.country')
                ->label(__('general.country'))
                ->placeholder(__('general.select_a_country'))
                ->searchable()
                ->options(fn () => Country::all()->mapWithKeys(fn ($country) => [$country->id => $country->flag.' '.$country->name]))
                ->optionsLimit(250),
            Forms\Components\TextInput::make('meta.phone')
                ->label(__('general.phone'))
                ->prefixIcon('heroicon-s-phone')
                ->type('tel')
                ->rule('phone:INTERNATIONAL')
                ->helperText(__('general.phone_format_international')),
            Forms\Components\Fieldset::make(__('general.scholar_profile'))
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('meta.orcid_url')
                                ->prefixIcon('academicon-orcid')
                                ->url()
                                ->label(__('general.orcid_id')),
                            Forms\Components\TextInput::make('meta.google_scholar_url')
                                ->prefixIcon('academicon-google-scholar')
                                ->url()
                                ->label(__('general.google_scholar')),
                            Forms\Components\TextInput::make('meta.scopus_url')
                                ->label(__('general.scopus_id'))
                                ->url()
                                ->prefixIcon('academicon-scopus-square'),
                        ]),
                ]),
        ];
    }

    public static function generalTableColumns(): array
    {
        return [
            IndexColumn::make('no')
                ->toggleable(),
            SpatieMediaLibraryImageColumn::make('profile')
                ->label(__('general.profile'))
                ->collection('profile')
                ->conversion('avatar')
                ->width(50)
                ->height(50)
                ->extraCellAttributes([
                    'style' => 'width: 1px',
                ])
                ->circular()
                ->defaultImageUrl(fn (Model $record): string => $record->getFilamentAvatarUrl())
                ->toggleable(),
            TextColumn::make('email')
                ->label(__('general.email'))
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('full_name')
                ->label(__('general.full_name'))
                ->searchable(
                    query: fn ($query, $search) => $query
                        ->where('given_name', 'LIKE', "%{$search}%")
                        ->orWhere('family_name', 'LIKE', "%{$search}%")
                )
                ->toggleable(),
            TextColumn::make('role.name')
                ->label(__('general.role'))
                ->badge()
                ->limitList(3)
                ->listWithLineBreaks(),
        ];
    }

    public static function tableActions($updateAction, $deleteAction): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->modalWidth('2xl')
                    ->mutateRecordDataUsing(function (array $data, Model $record) {
                        $data['meta'] = $record->getAllMeta();

                        return $data;
                    })
                    ->using(fn (array $data, Model $record) => $updateAction::run($record, $data)),
                DeleteAction::make()
                    ->using(
                        fn (Model $record) => $deleteAction::run($record)
                    ),
            ]),
        ];
    }
}
