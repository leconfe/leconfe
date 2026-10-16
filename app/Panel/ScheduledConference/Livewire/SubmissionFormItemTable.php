<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\SubmissionFormItem;
use App\Tables\Columns\IndexColumn;
use Closure;
use Filament\Forms\Components\Checkbox;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class SubmissionFormItemTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function mount() {}

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return SubmissionFormItem::query()
            ->ordered();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->reorderable('order_column')
            ->heading('Submission Form')
            ->columns([
                IndexColumn::make('#'),
                TextColumn::make('name')
                    ->getStateUsing(fn ($record) => $record->translated('name')),
                IconColumn::make('required')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('preview')
                    ->label(__('general.preview'))
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->color('gray')
                    ->visible(SubmissionFormItem::exists())
                    ->modalSubmitAction(false)
                    ->form(function (Form $form) {
                        return $form->schema([
                            ...SubmissionFormItem::buildFormSchema(),
                        ]);
                    }),
                CreateAction::make()
                    ->label('New Item')
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(function ($data) {
                        $record = new SubmissionFormItem;
                        $data = $record->syncTranslatableState($data);
                        $record->fill($data);
                        $record->save();

                        if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                            $record->setManyMeta($data['meta']);
                        }

                        $record->persistTranslatableState();

                        return $record;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(MaxWidth::ExtraLarge)
                        ->mutateRecordDataUsing(function (SubmissionFormItem $record, array $data): array {
                            $data['meta'] = $record->getAllMeta()->toArray();

                            return $record->fillTranslatableFormState($data, ['meta.name', 'meta.description', 'meta.response_options']);
                        })
                        ->form(fn (Form $form) => $this->form($form))
                        ->using(function (SubmissionFormItem $record, array $data) {
                            $data = $record->syncTranslatableState($data);
                            $record->update($data);

                            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                                $record->setManyMeta($data['meta']);
                            }

                            $record->persistTranslatableState();

                            return $record;
                        }),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public function form(Form $form): Form
    {
        $choiceTypes = [SubmissionFormItem::TYPE_SELECT, SubmissionFormItem::TYPE_RADIO, SubmissionFormItem::TYPE_CHECKBOX];

        return $form
            ->schema([
                MultilanguageComponent::make([
                    TextInput::make('meta.name')
                        ->required()
                        ->label('Item Name'),
                    Textarea::make('meta.description')
                        ->label('Description and Instructions'),
                ]),
                Checkbox::make('required')
                    ->label('Indicates required item'),
                Select::make('type')
                    ->required()
                    ->reactive()
                    ->options(fn () => SubmissionFormItem::getOptions())
                    ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! array_key_exists($value, SubmissionFormItem::getOptions())) {
                            $fail('Option unavailable');
                        }
                    })
                    ->label('Item Type'),
                MultilanguageComponent::make([
                    Repeater::make('meta.response_options')
                        ->label('Response Options')
                        ->simple(
                            TextInput::make('text')
                                ->required(),
                        )
                        // Only the primary language starts with an empty option; translations are optional.
                        ->defaultItems(fn (Repeater $component): int => ($component->getMeta('primary') ?? true) ? 1 : 0)
                        ->reorderable()
                        ->required()
                        ->validationMessages([
                            'required' => 'The :attribute field is required when Item Type is Checkbox, Radio Button, or Drop down',
                        ]),
                ])
                    ->visible(fn (Get $get) => in_array($get('type'), $choiceTypes)),
            ]);
    }
}
