<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Facades\Setting;
use App\Managers\PaymentManager;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Tables\Columns\IndexColumn;
use BladeUI\Icons\Components\Icon;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Livewire;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
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
            ->query($this->getTableQuery())
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('#'),
                TextColumn::make('name')
                    ->getStateUsing(fn($record) => $record->getMeta('name')),
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
                    ->form(function(Form $form){
                        return $form->schema([
                            ...PaymentFormItem::buildFormSchema($this->paymentType),
                        ]);
                    }),
                CreateAction::make()
                    ->label('New Item')
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn(Form $form) => $this->form($form))
                    ->using(function ($data) {
                        $record = new PaymentFormItem;
                        $record->fill($data);
                        $record->payment_type = $this->paymentType;
                        $record->save();

                        if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                            $record->setManyMeta($data['meta']);
                        }

                        return $record;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(MaxWidth::ExtraLarge)
                        ->mutateRecordDataUsing(function (PaymentFormItem $record, array $data): array {
                            $data['meta'] = $record->getAllMeta()->toArray();

                            return $data;
                        })
                        ->form(fn(Form $form) => $this->form($form))
                        ->using(function (PaymentFormItem $record, array $data) {
                            $record->update($data);

                            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                                $record->setManyMeta($data['meta']);
                            }

                            return $record;
                        }),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('meta.name')
                    ->required()
                    ->label('Item Name'),
                Textarea::make('meta.description')
                    ->label('Description and Instructions'),
                Checkbox::make('required')
                    ->label('Indicates required item'),
                Select::make('type')
                    ->required()
                    ->reactive()
                    ->options(fn() => PaymentFormItem::getOptions())
                    ->rule(fn(): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! array_key_exists($value, PaymentFormItem::getOptions())) {
                            $fail('Option unavailable');
                        }
                    })
                    ->label('Item Type'),
                Repeater::make('meta.response_options')
                    ->simple(
                        TextInput::make('text')
                            ->required(),
                    )
                    // ->placeholder('New Option')
                    ->visible(fn(Get $get) => in_array($get('type'), [PaymentFormItem::TYPE_SELECT, PaymentFormItem::TYPE_RADIO, PaymentFormItem::TYPE_CHECKBOX]))
                    ->reorderable()
                    ->requiredIf('type', [PaymentFormItem::TYPE_SELECT, PaymentFormItem::TYPE_RADIO, PaymentFormItem::TYPE_CHECKBOX])
                    ->validationMessages([
                        'required_if' => 'The :attribute field is required when Item Type is Checkbox, Radio Button, or Drop down',
                    ])
            ]);
    }
}
