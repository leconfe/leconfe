<?php

namespace App\Panel\ScheduledConference\Resources;

use App\Actions\Speakers\SpeakerRoleCreateAction;
use App\Actions\Speakers\SpeakerRoleUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\SpeakerRole;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SpeakerRoleResource extends Resource
{
    protected static bool $isDiscovered = false;

    protected static ?string $model = SpeakerRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static string $roleType = 'speaker';

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
                            fn (?Model $record) => Rule::unique(SpeakerRole::class, 'name')
                                ->where('scheduled_conference_id', app()->getCurrentScheduledConference()->getKey())
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
                    ->formatStateUsing(fn (SpeakerRole $record) => $record->translated('name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('speakers_count')
                    ->label(__('general.speakers'))
                    ->counts('speakers')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(fn (SpeakerRole $record, array $data) => $record->fillTranslatableFormState($data, ['name']))
                    ->using(fn (SpeakerRole $record, array $data) => SpeakerRoleUpdateAction::run($record, $data)),
                Tables\Actions\DeleteAction::make()
                    ->using(function (SpeakerRole $record, Tables\Actions\DeleteAction $action) {
                        try {
                            $speakerCount = $record->speakers()->count();
                            if ($speakerCount > 0) {
                                throw new \Exception(__('general.cannot_delete_speakers_role', ['variable' => $record->translated('name')]));
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
            ->heading(__('general.speaker_roles_table'))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(fn (array $data) => SpeakerRoleCreateAction::run($data))
                    ->label(__('general.new_speaker_role'))
                    ->modalHeading(__('general.new_speaker_role')),
            ]);
    }

    public static function getPages(): array
    {
        return [];
    }
}
