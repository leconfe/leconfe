<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Forms\Form;
use App\Models\PaymentFee;
use App\Models\PaymentFeeFormItem;
use App\Tables\Columns\IndexColumn;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PaymentFeeFormItemTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public PaymentFee $record;

    public function mount(PaymentFee $record): void
    {
        $this->form->fill([]);
    }

    public function render()
    {
        return view('tables.table');
    }

    public function form(Form $form)
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
                    ->options(fn () => PaymentFeeFormItem::getOptions())
                    ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! array_key_exists($value, PaymentFeeFormItem::getOptions())) {
                            $fail('Option unavailable');
                        }
                    })
                    ->label('Item Type'),
                TagsInput::make('meta.response_options')
                    ->placeholder('New Option')
                    ->visible(fn (Get $get) => in_array($get('type'), [PaymentFeeFormItem::TYPE_SELECT, PaymentFeeFormItem::TYPE_RADIO, PaymentFeeFormItem::TYPE_CHECKBOX]))
                    ->reorderable()
                    ->requiredIf('type', [PaymentFeeFormItem::TYPE_SELECT, PaymentFeeFormItem::TYPE_RADIO, PaymentFeeFormItem::TYPE_CHECKBOX])
                    ->validationMessages([
                        'required_if' => 'The :attribute field is required when Item Type is Checkbox, Radio Button, or Drop down',
                    ]),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->heading('Payment Fee Form Items')
            ->query(
                $this->record->formItems()->getQuery()
                    ->with(['meta'])
                    ->ordered()
            )
            ->reorderable('order_column')
            ->paginated(false)
            ->columns([
                IndexColumn::make('#'),
                TextColumn::make('name')
                    ->getStateUsing(fn ($record) => $record->getMeta('name')),
            ])
            ->filters([
                // ...
            ])
            ->headerActions([
                Action::make('create')
                    ->form(fn ($form) => $this->form($form))
                    ->hidden(fn () => $this->record->formItems->count())
                    ->action(function (array $data, Action $action) {
                        try {
                            DB::beginTransaction();

                            $item = $this->record->formItems()->create($data);

                            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                                $item->setManyMeta($data['meta']);
                            }

                            DB::commit();

                            $action->successNotificationTitle('Item Created.');

                            $action->success();
                        } catch (\Throwable $th) {

                            DB::rollBack();

                            $action->failureNotificationTitle($th->getMessage());

                            $action->failure();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn () => $this->record->formItems->count())
                    ->mutateRecordDataUsing(function (PaymentFeeFormItem $record, array $data): array {
                        $data['meta'] = $record->getAllMeta();

                        return $data;
                    })
                    ->form(fn ($form) => $this->form($form))
                    ->using(function (array $data, PaymentFeeFormItem $record, EditAction $action) {
                        try {
                            DB::beginTransaction();

                            $record->update($data);

                            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                                $record->setManyMeta($data['meta']);
                            }

                            DB::commit();

                            $action->successNotificationTitle('Item Updated.');
                        } catch (\Throwable $th) {

                            DB::rollBack();

                            $action->failure();
                        }
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                // ...
            ]);
    }
}
