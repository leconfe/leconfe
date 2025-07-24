<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Models\ReviewFormItem;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component as FormComponent;
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
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ReviewFormTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('panel.scheduledConference.livewire.review-form-table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ReviewFormItem::query()->ordered())
            ->reorderable('order_column')
            ->columns([
                TextColumn::make('label')
                    ->label(__('scheduled_conference.label'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('weight')
                    ->label(__('scheduled_conference.weight'))
                    ->getStateUsing(fn (ReviewFormItem $record) => $record->isEnableScoring() ? $record->weight : '-')
                    ->searchable(),
            ])
            ->headerActions([
                Action::make('form_preview')
                    ->label(__('scheduled_conference.form_preview'))
                    ->icon('heroicon-m-eye')
                    ->modalWidth(MaxWidth::TwoExtraLarge)
                    ->closeModalByClickingAway()
                    ->form(function (Form $form) {
                        return $form->schema(ReviewFormItem::ordered()->lazy()->map(fn (ReviewFormItem $item) => $item->getFormField())->toArray());
                    }),
                CreateAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(function ($data) {
                        try {
                            DB::beginTransaction();

                            $record = ReviewFormItem::create($data);
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
                    ->form(fn (Form $form) => $this->form($form))
                    ->mutateRecordDataUsing(function (ReviewFormItem $record, array $data) {
                        $data['meta'] = $record->getAllMeta()->toArray();

                        return $data;
                    })
                    ->using(function (ReviewFormItem $record, array $data) {
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
                        ->icon('heroicon-m-clipboard-document-check')
                        ->color('warning')
                        ->form(fn (Form $form) => $this->form($form)->model(null))
                        ->fillForm(fn ($record) => [
                            ...$record->attributesToArray(),
                            'meta' => $record->getAllMeta()->toArray(),
                        ])
                        ->action(function (array $data) {
                            try {
                                DB::beginTransaction();

                                $record = ReviewFormItem::create($data);
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
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
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
                    ->label(__('scheduled_conference.review_form_item.reviewer_required_input_label')),
                Select::make('type')
                    ->label(__('scheduled_conference.type'))
                    ->required()
                    ->live()
                    ->options(fn () => ReviewFormItem::getOptions())
                    ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! array_key_exists($value, ReviewFormItem::getOptions())) {
                            $fail(__('scheduled_conference.option_unavailable'));
                        }
                    }),
                $this->getSchemaTypeSelect(),
                $this->getSchemaTypeCheckbox(),
                $this->getSchemaTypeRadio(),
            ]);
    }

    protected function getSchemaTypeSelect(): FormComponent
    {
        return Grid::make(1)
            ->visible(fn (Get $get) => $get('type') == ReviewFormItem::TYPE_SELECT)
            ->schema([
                TextInput::make('weight')
                    ->label(__('scheduled_conference.review_form_item.weight_input_label'))
                    ->hintIcon('heroicon-m-question-mark-circle', __('scheduled_conference.review_form_item.weight_input_helper_full'))
                    ->numeric()
                    ->rule(fn (?ReviewFormItem $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                        $currentWeight = ReviewFormItem::query()
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->getKey()))
                            ->whereNotNull('weight')
                            ->sum('weight');

                        $totalWeight = $currentWeight + $value;

                        if ($totalWeight > 100) {
                            $fail(__('validation.weight_over', ['attribute' => $totalWeight]));
                        }
                    })
                    ->maxValue(100)
                    ->minValue(0)
                    ->suffix('%'),
                Repeater::make('meta.select_options')
                    ->label(__('scheduled_conference.review_form_item.select_options_input_label'))
                    ->hint(__('scheduled_conference.review_form_item.select_options_input_helper'))
                    ->hintIcon('heroicon-m-question-mark-circle', __('scheduled_conference.review_form_item.select_options_input_helper_full'))
                    ->required()
                    ->columns(4)
                    ->reorderable()
                    ->schema([
                        TextInput::make('value')
                            ->label(__('scheduled_conference.value'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required()
                            ->distinct(),
                        TextInput::make('label')
                            ->label(__('scheduled_conference.label'))
                            ->required()
                            ->columnSpan([
                                'lg' => 3,
                            ]),
                    ])
                    ->maxItems(10),
            ]);
    }

    protected function getSchemaTypeCheckbox(): FormComponent
    {
        return Grid::make(1)
            ->visible(fn (Get $get) => $get('type') == ReviewFormItem::TYPE_CHECKBOX)
            ->schema([
                Repeater::make('meta.checkbox_options')
                    ->label(__('scheduled_conference.review_form_item.checkbox_options_input_label'))
                    ->simple(
                        TextInput::make('option')
                            ->required()
                            ->label(__('scheduled_conference.option'))
                    ),
            ]);
    }

    protected function getSchemaTypeRadio(): FormComponent
    {
        return Grid::make(1)
            ->visible(fn (Get $get) => $get('type') == ReviewFormItem::TYPE_RADIO)
            ->schema([
                Repeater::make('meta.radio_options')
                    ->label(__('scheduled_conference.review_form_item.radio_options_input_label'))
                    ->simple(
                        TextInput::make('option')
                            ->required()
                            ->label(__('scheduled_conference.option'))
                    ),
            ]);
    }
}
