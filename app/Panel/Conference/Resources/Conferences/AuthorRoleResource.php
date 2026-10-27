<?php

namespace App\Panel\Conference\Resources\Conferences;

use App\Actions\Authors\AuthorRoleCreateAction;
use App\Actions\Authors\AuthorRoleUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\AuthorRole;
use App\Panel\Conference\Resources\Conferences\AuthorRoleResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AuthorRoleResource extends Resource
{
    protected static bool $isDiscovered = false;

    protected static ?string $model = AuthorRole::class;

    protected static ?string $navigationGroup = 'Conferences';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return 'Author';
    }

    public static string $roleType = 'author';

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()
            ->with('translate')
            ->orderBy('order_column');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                MultilanguageComponent::make([
                    TextInput::make('name')
                        ->label(__('general.name'))
                        ->required()
                        ->rule(
                            fn (?Model $record) => Rule::unique(AuthorRole::class, 'name')
                                ->where('conference_id', app()->getCurrentConference()->getKey())
                                ->ignore($record),
                            fn (TextInput $component): bool => ($component->getMeta('primary') ?? true),
                        ),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('no'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('general.name'))
                    ->formatStateUsing(fn (AuthorRole $record) => $record->translated('name'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereTranslatableLike('name', $search))
                    ->sortable(),
                Tables\Columns\TextColumn::make('authors_count')
                    ->label(__('general.authors'))
                    ->counts('authors')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(fn (AuthorRole $record, array $data) => $record->fillTranslatableFormState($data, ['name']))
                    ->using(fn (AuthorRole $record, array $data) => AuthorRoleUpdateAction::run($record, $data)),
                Tables\Actions\DeleteAction::make()
                    ->using(function (AuthorRole $record, Tables\Actions\DeleteAction $action) {
                        try {
                            $authorCount = $record->authors()->count();
                            if ($authorCount > 0) {
                                throw new \Exception(__('general.cannot_delete_role', ['variable' => $record->translated('name')]));
                            }

                            return $record->delete();
                        } catch (\Throwable $th) {
                            $action->failureNotificationTitle($th->getMessage());
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(fn (array $data) => AuthorRoleCreateAction::run($data))
                    ->label(__('general.new_author_role'))
                    ->modalHeading(__('general.new_author_role')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAuthorRoles::route('/'),
        ];
    }
}
