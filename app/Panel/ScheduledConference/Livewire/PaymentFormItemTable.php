<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Filament\Forms\Components\MultilanguageComponent;
use App\Filament\Forms\Components\ResponseOptionsRepeater;
use App\Models\PaymentFormItem;
use App\Tables\Columns\IndexColumn;
use Closure;
use Filament\Forms\Components\Checkbox;
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

class PaymentFormItemTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public int $paymentType;

    public function mount(int $paymentType) {}

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return PaymentFormItem::query()
            ->paymentType($this->paymentType)
            ->ordered();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('general.form_item'))
            ->query($this->getTableQuery())
            ->reorderable('order_column')
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
                    ->visible(PaymentFormItem::paymentType($this->paymentType)->exists())
                    ->modalSubmitAction(false)
                    ->form(function (Form $form) {
                        return $form->schema([
                            ...PaymentFormItem::buildFormSchema($this->paymentType),
                        ]);
                    }),
                CreateAction::make()
                    ->label(__('general.new_item'))
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(function ($data) {
                        $record = new PaymentFormItem;
                        $data = $record->syncTranslatableState($data);
                        $record->fill($data);
                        $record->payment_type = $this->paymentType;
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
                        ->mutateRecordDataUsing(function (PaymentFormItem $record, array $data): array {
                            $data['meta'] = $record->getAllMeta()->toArray();

                            return $record->fillTranslatableFormState($data, ['meta.name', 'meta.description', 'meta.response_options']);
                        })
                        ->form(fn (Form $form) => $this->form($form))
                        ->using(function (PaymentFormItem $record, array $data) {
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
        $choiceTypes = [PaymentFormItem::TYPE_SELECT, PaymentFormItem::TYPE_RADIO, PaymentFormItem::TYPE_CHECKBOX];

        return $form
            ->schema([
                MultilanguageComponent::make([
                    TextInput::make('meta.name')
                        ->required()
                        ->label(__('general.item_name')),
                    Textarea::make('meta.description')
                        ->label(__('general.description_and_instructions')),
                ]),
                Checkbox::make('required')
                    ->label(__('general.indicates_required_item')),
                Select::make('type')
                    ->required()
                    ->reactive()
                    ->options(fn () => PaymentFormItem::getOptions())
                    ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! array_key_exists($value, PaymentFormItem::getOptions())) {
                            $fail(__('general.option_unavailable'));
                        }
                    })
                    ->label(__('general.item_type')),
                ResponseOptionsRepeater::make('meta.response_options')
                    ->visible(fn (Get $get) => in_array($get('type'), $choiceTypes)),
            ]);
    }
}
