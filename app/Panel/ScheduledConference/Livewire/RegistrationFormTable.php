<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Models\Registration;
use App\Models\RegistrationForm;
use App\Tables\Columns\IndexColumn;
use Closure;
use Filament\Forms\Components\Component as FormComponent;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RegistrationFormTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function mount() {
        // dd(RegistrationForm::createDefaultFormItems());
    }

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return RegistrationForm::query()->ordered();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('label')
                    ->grow(false),
                TextColumn::make('type')
                    ->grow(false)
                    ->getStateUsing(fn(RegistrationForm $record) => RegistrationForm::getTypeLabel($record->type)),
            ])
            ->headerActions([
                Action::make('form_preview')
                    ->label(__('scheduled_conference.form_preview'))
                    ->icon('heroicon-m-eye')
                    ->modalWidth(MaxWidth::TwoExtraLarge)
                    ->closeModalByClickingAway()
                    ->form(function (Form $form) {
                        return $form->schema(RegistrationForm::getFormSchema())->disabled();
                    }),
                CreateAction::make()
                    ->label(__('scheduled_conference.registration_form_table.create_action_label'))
                    ->modalHeading(__('scheduled_conference.registration_form_table.create_action_label'))
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn(Form $form) => $this->form($form))
                    ->using(function ($data) {
                        try {
                            DB::beginTransaction();

                            $record = RegistrationForm::create($data);
                            if (data_get($data, 'meta')) {
                                $record->setManyMeta(data_get($data, 'meta'));
                            }

                            DB::commit();
                        } catch (\Throwable $th) {
                            DB::rollBack();

                            throw $th;
                        }

                        return $record;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->hidden(fn(RegistrationForm $record) => $record->isDefault())
                    ->form(fn(Form $form) => $this->form($form))
                    ->mutateRecordDataUsing(function (RegistrationForm $record, array $data) {
                        $data['meta'] = $record->getAllMeta()->toArray();

                        return $data;
                    })
                    ->using(function (RegistrationForm $record, array $data) {
                        try {
                            DB::beginTransaction();

                            if (data_get($data, 'meta')) {
                                $record->setManyMeta(data_get($data, 'meta'));
                            }
                            $record->fill($data);
                            $record->save();

                            DB::commit();
                        } catch (\Throwable $th) {
                            DB::rollBack();

                            throw $th;
                        }

                        return $record;
                    }),
                ActionGroup::make([
                    Action::make('copy')
                        ->label(__('scheduled_conference.copy'))
                        ->modalWidth(MaxWidth::ExtraLarge)
                        ->hidden(fn(RegistrationForm $record) => $record->isDefault())
                        ->icon('heroicon-m-clipboard-document-check')
                        ->color('warning')
                        ->form(fn(Form $form) => $this->form($form)->model(null))
                        ->fillForm(fn($record) => [
                            ...$record->attributesToArray(),
                            'meta' => $record->getAllMeta()->toArray(),
                        ])
                        ->action(function (array $data) {
                            try {
                                DB::beginTransaction();

                                $record = RegistrationForm::create($data);
                                if (data_get($data, 'meta')) {
                                    $record->setManyMeta(data_get($data, 'meta'));
                                }

                                DB::commit();
                            } catch (\Throwable $th) {
                                DB::rollBack();

                                throw $th;
                            }

                            return $record;
                        }),
                    DeleteAction::make()
                        ->hidden(fn(RegistrationForm $record) => $record->isDefault()),
                ]),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('label')
                    ->label(__('scheduled_conference.label'))
                    ->required(),
                Textarea::make('meta.description')
                    ->label(__('scheduled_conference.description'))
                    ->autosize(),
                Checkbox::make('meta.required')
                    ->label(__('scheduled_conference.required')),
                Select::make('type')
                    ->label(__('scheduled_conference.type'))
                    ->required()
                    ->live()
                    ->options(fn() => RegistrationForm::getOptions())
                    ->rule(fn(): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! array_key_exists($value, RegistrationForm::getOptions())) {
                            $fail(__('scheduled_conference.option_unavailable'));
                        }
                    }),
                $this->getSchemaOptions(),
            ]);
    }

    protected function getSchemaOptions(): FormComponent
    {
        return Grid::make(1)
            ->visible(fn(Get $get) => in_array($get('type'), [RegistrationForm::TYPE_SELECT, RegistrationForm::TYPE_CHECKBOX, RegistrationForm::TYPE_RADIO]))
            ->schema([
                Repeater::make('meta.options')
                    ->required()
                    ->minItems(1)
                    ->label(__('scheduled_conference.options'))
                    ->simple(
                        TextInput::make('option')
                            ->required()
                            ->label(__('scheduled_conference.option'))
                    )
            ]);
    }
}
