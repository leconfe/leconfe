<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use App\Models\Conference;
use App\Models\Serie;
use App\Models\Submission;
use App\Tables\Columns\IndexColumn;
use Livewire\Component;
use Filament\Forms;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Squire\Models\Country;

class ContributorForm extends Component
{
    public static function generalFormField(Model $modelType): array
    {
        return [
            Forms\Components\SpatieMediaLibraryFileUpload::make('profile')
                ->label(__('translation.profile.labelProfilePhoto'))
                ->image()
                ->key('profile')
                ->collection('profile')
                ->conversion('thumb')
                ->alignCenter()
                ->columnSpan([
                    'lg' => 2,
                ]),
            Forms\Components\TextInput::make('given_name')
                ->label(__('translation.profile.labelGivenName'))
                ->required(),
            Forms\Components\TextInput::make('family_name')
                ->label(__('translation.profile.labelFamilyName')),
            Forms\Components\TextInput::make('email')
                ->label(__('translation.profile.labelEmail'))
                ->required()
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: function (Unique $rule) use ($modelType) {
                        return $rule
                            ->when($modelType instanceof Conference, fn ($rule) => $rule->where('conference_id', $modelType->getKey()))
                            ->when($modelType instanceof Serie, fn ($rule) => $rule->where('serie_id', $modelType->getKey()))
                            ->when($modelType instanceof Submission, fn ($rule) => $rule->where('submission_id', $modelType->getKey()));
                    }
                )
                ->columnSpan([
                    'lg' => 2,
                ]),
        ];
    }

    public static function additionalFormField(): array
    {
        return [
            Forms\Components\TagsInput::make('meta.expertise')
                ->label(__('translation.profile.labelExpertise'))
                ->placeholder('')
                ->columnSpan([
                    'lg' => 2,
                ]),
            Forms\Components\TextInput::make('meta.affiliation')
                ->label(__('translation.profile.labelAffiliation'))
                ->prefixIcon('heroicon-s-building-library')
                ->placeholder(__('translation.profile.placeholderUniversityofJakarta'))
                ->columnSpan([
                    'lg' => 2,
                ]),
            Forms\Components\Select::make('meta.country')
                ->label(__('translation.profile.labelCountry'))
                ->placeholder(__('translation.profile.placeholderSelectaCountry'))
                ->searchable()
                ->options(fn () => Country::all()->mapWithKeys(fn ($country) => [$country->id => $country->flag.' '.$country->name]))
                ->optionsLimit(250),
            Forms\Components\TextInput::make('meta.phone')
                ->label(__('translation.profile.labelPhone'))
                ->prefixIcon('heroicon-s-phone')
                ->type('tel')
                ->rule('phone:INTERNATIONAL')
                ->helperText(__('translation.profile.helperPhone')),
            Forms\Components\Fieldset::make(__('translation.profile.titleScholarProfile'))
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('meta.orcid_id')
                                ->prefixIcon('academicon-orcid')
                                ->placeholder('0000-0000-0000-0000')
                                ->label('ORCID iD'),
                            Forms\Components\TextInput::make('meta.google_scholar_id')
                                ->prefixIcon('academicon-google-scholar')
                                ->placeholder('abcdefgh1234')
                                ->label('Google Scholar'),
                            Forms\Components\TextInput::make('meta.scopus_id')
                                ->label('Scopus ID')
                                ->placeholder('7005557890')
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
                ->label(__('translation.speakerResource.contributorFormLabelProfile'))
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
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('full_name')
                ->label(__('translation.speakerResource.contributorFormLabelFullname'))
                ->searchable(
                    query: fn ($query, $search) => $query
                        ->where('given_name', 'LIKE', "%{$search}%")
                        ->orWhere('family_name', 'LIKE', "%{$search}%")
                )
                ->toggleable(),
            TextColumn::make('role.name')
                ->label(__('translation.speakerResource.contributorFormLabelRole'))
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
