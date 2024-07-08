<?php

namespace App\Panel\Conference\Livewire\Submissions\Components;

use App\Models\Author;
use App\Models\Submission;
use Filament\Tables\Table;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use App\Actions\Authors\AuthorCreateAction;
use App\Actions\Authors\AuthorDeleteAction;
use App\Actions\Authors\AuthorUpdateAction;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Forms\Components\Actions\Action as FormAction;
use App\Panel\Conference\Resources\Conferences\AuthorRoleResource;

class ContributorList extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Submission $submission;

    public bool $viewOnly = false;

    public function getQuery(bool $submissionRelated = true): Builder
    {
        return Author::query()
            ->whereSubmissionId($this->submission->getKey())
            ->with(['role', 'media', 'meta'])
            ->orderBy('order_column');
    }

    public function selectAuthorField(): Select
    {
        return Select::make('author_id')
            ->label(__('translation.submissions.contributtorListLabelSelectExisting'))
            ->placeholder(__('translation.submissions.contributtorListPlaceHolderSelect'))
            ->preload()
            ->native(false)
            ->searchable()
            ->allowHtml()
            ->options(function () {
                $authors = $this->getQuery()->pluck('email')->toArray();

                return Author::query()
                    ->whereNotIn('email', $authors)
                    ->get()
                    ->mapWithKeys(fn (Author $author) => [$author->getKey() => static::renderSelectAuthor($author)])
                    ->toArray();
            })
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
                        ->get()
                        ->mapWithKeys(fn (Author $author) => [$author->getKey() => static::renderSelectAuthor($author)])
                        ->toArray();
                }
            )
            ->live()
            ->afterStateUpdated(function ($state, $livewire) {
                if (! $state) {
                    return;
                }
                $author = Author::with(['meta', 'role' => fn ($query) => $query->withoutGlobalScopes()])->findOrFail($state);
                $role = AuthorRoleResource::getEloquentQuery()->whereName($author?->role?->name)->first();

                $formData = [ 
                    'author_id' => $state,
                    'given_name' => $author->given_name,
                    'family_name' => $author->family_name,
                    'email' => $author->email,
                    'author_role_id' => $role->id ?? null,
                    'meta' => $author->getAllMeta()
                ];
                return $livewire->mountedTableActionsData[0] = $formData;
            })
            ->columnSpanFull();
    }

    public function getContributorFormSchema(): array
    {
        return [
            Grid::make()
                ->schema([
                    $this->selectAuthorField(),
                    ...ContributorForm::generalFormField($this->submission),
                    Select::make('author_role_id')
                        ->relationship(
                            name: 'role',
                            titleAttribute: 'name',
                        )
                        ->createOptionForm(fn ($form) => AuthorRoleResource::form($form))
                        ->createOptionAction(
                            fn (FormAction $action) => $action->color('primary')
                                ->modalWidth('xl')
                                ->modalHeading(__('translation.submissions.contributtorListModalHeadingCreate'))
                        )
                        ->preload()
                        ->required()
                        ->columnSpanFull()
                        ->searchable(),
                    ...ContributorForm::additionalFormField(),
                ])
                ->columnSpan([
                    'lg' => 2,
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable(
                fn () => $this->viewOnly ? false : 'order_column'
            )
            ->heading(__('translation.submissions.authorStepContributors'))
            ->query(
                fn (): Builder => $this->getQuery()
            )
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth('3xl')
                        ->hidden(
                            fn (Model $record): bool => $record->email == auth()->user()->email
                        )
                        ->mutateRecordDataUsing(function (array $data, Model $record) {
                            $data['meta'] = $record->getAllMeta();
                            return $data;
                        })
                        ->form($this->getContributorFormSchema())
                        ->using(fn (array $data, Author $record) => AuthorUpdateAction::run($data, $record)),
                    DeleteAction::make()
                        ->using(fn (array $data, Model $record) => AuthorDeleteAction::run($record, $data))
                        ->hidden(
                            fn (Model $record): bool => $record->email == auth()->user()->email
                        ),
                ])
                ->hidden($this->viewOnly),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('translation.submissions.authorStepNewContributors'))
                    ->modalWidth('2xl')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading(__('translation.submissions.modalHeadingAddContributor'))
                    ->successNotificationTitle(__('translation.submissions.successNotificationTitlContributoradded'))
                    ->form($this->getContributorFormSchema())
                    ->using(function (array $data) {
                        $author = Author::whereSubmissionId($this->submission->getKey())->email($data['email'])->first();
                        if (! $author) {
                            $author = AuthorCreateAction::run($this->submission, $data);
                        }
                        
                        return $author;
                    })
                    ->hidden($this->viewOnly),
            ])
            ->columns([
                Split::make([
                    SpatieMediaLibraryImageColumn::make('profile')
                        ->grow(false)
                        ->collection('profile')
                        ->conversion('avatar')
                        ->width(50)
                        ->height(50)
                        ->defaultImageUrl(
                            fn (Model $record): string => $record->getFilamentAvatarUrl()
                        )
                        ->extraCellAttributes([
                            'style' => 'width: 1px',
                        ])
                        ->circular()
                        ->toggleable(! $this->viewOnly),
                    Stack::make([
                        TextColumn::make('fullName')
                            ->formatStateUsing(function (Model $record) {
                                if ($record->email == auth()->user()->email) {
                                    return $record->fullName.' (You)';
                                }

                                return $record->fullName;
                            }),
                        TextColumn::make('affiliation')
                            ->size('xs')
                            ->getStateUsing(
                                fn (Model $record) => $record->getMeta('affiliation')
                            )
                            ->icon('heroicon-o-building-library')
                            ->extraAttributes([
                                'class' => 'text-xs',
                            ])
                            ->color('gray'),
                        TextColumn::make('email')
                            ->size('xs')
                            ->extraAttributes([
                                'class' => 'text-xs',
                            ])
                            ->color('gray')
                            ->icon('heroicon-o-envelope')
                            ->alignStart(),
                    ])->space(1),
                    TextColumn::make('role.name')
                        ->badge()
                        ->alignEnd(),
                ]),
            ]);
    }

    public static function renderSelectAuthor(Author $author): string
    {
        $author->load('submission');
        return view('forms.select-contributor-submission', ['contributor' => $author])->render();
    }

    public function render()
    {
        return view('panel.conference.livewire.submissions.components.contributor-list');
    }
}
