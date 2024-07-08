<?php

namespace App\Panel\Series\Resources;

use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\SpeakerRole;
use Filament\Resources\Resource;
use App\Tables\Columns\IndexColumn;
use Illuminate\Validation\Rules\Unique;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class SpeakerRoleResource extends Resource
{
    protected static bool $isDiscovered = false;

    protected static ?string $model = SpeakerRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static string $roleType = 'speaker';

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
                    ->label(__('translation.speakerResource.speakerRoleResourceLabelName'))
                    ->required()
                    ->unique(modifyRuleUsing: function (Unique $rule) {
                        return $rule
                            ->where('serie_id', app()->getCurrentSerie()->getKey());
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
                    ->label(__('translation.speakerResource.speakerRoleResourceLabelName'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('speakers_count')
                    ->label(__('translation.speakerResource.speakerRoleResourceLabelSpeakers'))
                    ->counts('speakers')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->using(function (SpeakerRole $record, Tables\Actions\DeleteAction $action) {
                        try {
                            $speakerCount = $record->speakers()->count();
                            if ($speakerCount > 0) {
                                throw new \Exception(__('translation.speakerResource.speakerRoleResourceDeleteCannot').$record->name.__('translation.speakerResource.speakerRoleResourceDeleteThereAre'));
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
            ->heading(__('translation.speakerResource.speakerRoleResourceHeadingSpeakerRolesTable'))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        return $data;
                    })
                    ->label(__('translation.speakerResource.speakerRoleResourceLabelNewSpeakerRole'))
                    ->modalHeading(__('translation.speakerResource.speakerRoleResourceModalHeadingNewSpeakerRole')),
            ]);
    }

    public static function getPages(): array
    {
        return [];
    }
}
