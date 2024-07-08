<?php

namespace App\Panel\Conference\Resources\Conferences;

use App\Models\AuthorRole;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use App\Panel\Conference\Resources\Conferences\AuthorRoleResource\Pages;

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
            ->orderBy('order_column');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('translation.authorRoleResource.authorRoleResourceLabelName'))
                    ->required()
                    ->unique(modifyRuleUsing: function (Unique $rule) {
                        return $rule
                            ->where('conference_id', app()->getCurrentConference()->getKey());
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('no'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('translation.authorRoleResource.authorRoleResourceLabelName'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('authors_count')
                    ->label(__('translation.authorRoleResource.authorRoleResourceLabelAuthors'))
                    ->counts('authors')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->using(function (AuthorRole $record, Tables\Actions\DeleteAction $action) {
                        try {
                            $authorCount = $record->authors()->count();
                            if ($authorCount > 0) {
                                throw new \Exception(__('translation.authorRoleResource.authorRoleResourceDeleteActionCannot').$record->name.__('translation.authorRoleResource.authorRoleResourceDeleteActionThereAreAuthors'));
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
                    ->mutateFormDataUsing(function (array $data): array {
                        return $data;
                    })
                    ->label(__('translation.authorRoleResource.authorRoleResourceLabelNewAuthorRole'))
                    ->modalHeading(__('translation.authorRoleResource.authorRoleResourceLabelNewAuthorRole')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAuthorRoles::route('/'),
        ];
    }
}
