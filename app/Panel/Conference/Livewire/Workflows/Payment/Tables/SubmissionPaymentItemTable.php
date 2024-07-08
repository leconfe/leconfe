<?php

namespace App\Panel\Conference\Livewire\Workflows\Payment\Tables;

use App\Models\Enums\PaymentType;
use App\Models\PaymentItem;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;
use Squire\Models\Currency;

class SubmissionPaymentItemTable extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function render()
    {
        return view('panel.conference.livewire.workflows.payment.tables.payment-items');
    }

    public function table(Table $table): Table
    {
        $formField = [
            Hidden::make('type'),
            TextInput::make('name')
                ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelName'))
                ->required(),
            Textarea::make('description')
                ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelDescription'))
                ->autosize(),
            Repeater::make('fees')
                ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelFees'))
                ->required()
                ->schema([
                    Grid::make()
                        ->schema([
                            Select::make('currency_id')
                                ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelCurrency'))
                                ->searchable()
                                ->required()
                                ->disabled()
                                ->dehydrated(true)
                                ->options(Currency::pluck('name', 'id'))
                                ->optionsLimit(250)
                                ->distinct(),
                            TextInput::make('fee')
                                ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelFee'))
                                ->required()
                                ->minValue(1)
                                ->prefix(fn (Get $get) => $get('currency_id') ? Currency::find($get('currency_id'))->symbol_native : null)
                                ->numeric(),
                        ]),
                ])
                ->deletable(false)
                ->reorderable(false)
                ->addable(false)
                ->addActionLabel('Add Fee'),
        ];

        return $table
            ->query(
                PaymentItem::query()
                    ->where('type', PaymentType::Submission)
                    ->orderBy('order_column')
            )
            ->reorderable('order_column')
            ->paginated(false)
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('name')
                    ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelName'))
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelDescription'))
                    ->searchable()
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('translation.workflowPaymentSetting.submissionPaymentItemTableLabelNewPaymentItem'))
                    ->mountUsing(function (Form $form) {
                        $fees = collect(App::getCurrentConference()->getMeta('payment.supported_currencies'))
                            ->map(fn ($currency) => [
                                'currency_id' => $currency,
                                'fee' => 0,
                            ]);

                        $form->fill([
                            'fees' => $fees->toArray(),
                            'type' => PaymentType::Submission,
                        ]);
                    })
                    ->model(PaymentItem::class)
                    ->modalWidth('2xl')
                    ->form($formField),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    ->mutateRecordDataUsing(function ($data) {
                        $supportedCurrencies = App::getCurrentConference()->getMeta('payment.supported_currencies') ?? [];
                        $fees = collect($supportedCurrencies)
                            ->map(fn ($currency) => ['currency_id' => $currency, 'fee' => 0])
                            ->keyBy('currency_id')
                            ->merge(collect($data['fees'])->keyBy('currency_id'))
                            ->filter(fn ($fee) => in_array($fee['currency_id'], $supportedCurrencies))
                            ->toArray();
                        $data['fees'] = $fees;

                        return $data;
                    })
                    ->modalWidth('2xl')
                    ->form($formField),
                DeleteAction::make(),
            ])
            ->bulkActions([
                // ...
            ]);
    }
}
