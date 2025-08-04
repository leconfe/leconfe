<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Actions\Authors\AuthorCreateAction;
use App\Actions\Authors\AuthorDeleteAction;
use App\Actions\Authors\AuthorUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\Author;
use App\Models\Submission;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use App\Panel\Conference\Resources\Conferences\AuthorRoleResource;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContributorList extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Submission $submission;

    public bool $viewOnly = false;

    protected $listeners = ['refreshLivewire' => '$refresh'];

    public function getQuery(bool $submissionRelated = true): Builder
    {
        return Author::query()
            ->whereSubmissionId($this->submission->getKey())
            ->with(['role', 'media', 'meta'])
            ->orderBy('order_column');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Grid::make()
                ->schema([
                    Select::make('author_id')
                        ->label(__('general.select_existing_author'))
                        ->placeholder(__('general.select_author'))
                        ->preload()
                        ->native(false)
                        ->searchable()
                        ->allowHtml()
                        ->optionsLimit(10)
                        ->getSearchResultsUsing(
                            function (string $search) {
                                $authors = $this->getQuery()->pluck('email')->toArray();

                                return Author::query()
                                    ->with(['media', 'meta'])
                                    ->whereNotIn('email', $authors)
                                    ->where(fn ($query) => $query->where('given_name', 'LIKE', "%{$search}%")
                                        ->orWhere('family_name', 'LIKE', "%{$search}%")
                                        ->orWhere('email', 'LIKE', "%{$search}%"))
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->unique('email')
                                    ->mapWithKeys(fn (Author $author) => [$author->getKey() => static::renderSelectAuthor($author)])
                                    ->toArray();
                            }
                        )
                        ->live()
                        ->afterStateUpdated(function (string $state, Select $component, $livewire) {
                            if (! $state) {
                                return;
                            }

                            $form = $component->getContainer();

                            $author = Author::with(['meta', 'role' => fn ($query) => $query->withoutGlobalScopes()])->findOrFail($state);
                            $role = AuthorRoleResource::getEloquentQuery()->whereName($author?->role?->name)->first();

                            $formData = [
                                'author_id' => $state,
                                'given_name' => $author->given_name,
                                'family_name' => $author->family_name,
                                'email' => $author->email,
                                'author_role_id' => $role->id ?? null,
                                'meta' => $author->getAllMeta(),
                            ];

                            if ($author->getFirstMedia('profile')) {
                                $livewire->dispatch('update-profile-image', $author->getFirstMedia('profile')->getUrl());
                            }

                            return $form->fill($formData);
                        })
                        ->columnSpanFull(),
                    SpatieMediaLibraryFileUpload::make('profile')
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
                    MultilanguageComponent::make([
                        TextInput::make('meta.given_name')
                            ->label(__('general.given_name'))
                            ->required(),
                        TextInput::make('meta.family_name')
                            ->label(__('general.family_name')),
                        ]),
                        TextInput::make('email')
                        ->label(__('general.email'))
                        ->columnSpan([
                            'lg' => 2,
                        ])
                        ->required()
                        ->email(),
                    MultilanguageComponent::make([
                        TextInput::make('meta.public_name')
                        ->label(__('general.public_name'))
                        ->helperText(__('general.public_name_helper'))
                        ->columnSpan(['lg' => 2]),
                    ]),
                    
                    Select::make('author_role_id')
                        ->relationship(
                            name: 'role',
                            titleAttribute: 'name',
                        )
                        ->createOptionForm(fn ($form) => AuthorRoleResource::form($form))
                        ->createOptionAction(
                            fn (FormAction $action) => $action->color('primary')
                                ->modalWidth('xl')
                                ->modalHeading(__('general.create_author_role'))
                        )
                        ->preload()
                        ->required()
                        ->columnSpanFull()
                        ->searchable(),
                    ...ContributorForm::additionalFormField(),
                    Checkbox::make('primary_contact'),
                ])
                ->columnSpan([
                    'lg' => 2,
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('general.contributors'))
            ->emptyStateDescription(__('general.no_contributors'))
            ->query(
                fn (): Builder => $this->getQuery()
            )
            ->reorderable('order_column')
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth('3xl')
                        ->mutateRecordDataUsing(function (array $data, Author $record) {
                            $data['meta'] = $record->getAllMeta();
                            $data['primary_contact'] = $record->isPrimaryContact($this->submission);

                            return $data;
                        })
                        ->form(fn (Form $form) => $this->form($form))
                        ->using(function (array $data, Author $record) {
                            AuthorUpdateAction::run($data, $record);

                            if ($data['primary_contact']) {
                                $this->submission->setPrimaryContact($record);
                            }
                        }),
                    DeleteAction::make()
                        ->using(fn (array $data, Model $record) => AuthorDeleteAction::run($record, $data)),
                ])
                    ->hidden($this->viewOnly),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('general.new_contributor'))
                    ->modalWidth('2xl')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading(__('general.add_contributor'))
                    ->successNotificationTitle(__('general.contributor_added'))
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(function (array $data) {
                        $author = AuthorCreateAction::run($this->submission, $data);

                        $authorCount = Author::where('submission_id', $author->submission_id)->count();
                        if ($authorCount == 1) {
                            $this->submission->setPrimaryContact($author);
                        }

                        return $author;
                    })
                    ->hidden($this->viewOnly),
            ])
            ->columns([
                TextColumn::make('name')
                    ->getStateUsing(fn (Author $record) => $record->fullName),
                TextColumn::make('email')
                    ->size('xs')
                    ->color('gray')
                    ->alignStart(),
                TextColumn::make('role.name')
                    ->badge(),
                IconColumn::make('primary_contact')
                    ->getStateUsing(fn ($record) => $record->isPrimaryContact($this->submission))
                    ->icon(fn (bool $state): ?string => match ($state) {
                        true => 'heroicon-o-check-circle',
                        default => null,
                    })
                    ->color('success'),

            ]);
    }

    public static function renderSelectAuthor(Author $author): string
    {
        return view('forms.select-contributor', ['contributor' => $author])->render();
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.contributor-list');
    }
}
