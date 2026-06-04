<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Actions\Authors\AuthorCreateAction;
use App\Actions\Authors\AuthorDeleteAction;
use App\Actions\Authors\AuthorUpdateAction;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Models\Author;
use App\Models\Submission;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
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
                    SpatieMediaLibraryFileUpload::make('profile')
                        ->label(__('general.profile_picture'))
                        ->image()
                        ->key('profile')
                        ->collection('profile')
                        ->conversion('thumb')
                        ->alignCenter()
                        ->columnSpan([
                            'lg' => 2,
                        ]),
                    TextInput::make('given_name')
                        ->label(__('general.given_name'))
                        ->required(),
                    TextInput::make('family_name')
                        ->label(__('general.family_name')),
                    TextInput::make('email')
                        ->label(__('general.email'))
                        ->columnSpan([
                            'lg' => 2,
                        ])
                        ->required()
                        ->email(),
                    TextInput::make('meta.public_name')
                        ->label(__('general.public_name'))
                        ->helperText(__('general.public_name_helper'))
                        ->columnSpan(['lg' => 2]),
                    Select::make('author_role_id')
                        ->relationship(
                            name: 'role',
                            titleAttribute: 'name',
                        )
                        ->required()
                        ->columnSpanFull(),
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

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.contributor-list');
    }
}
