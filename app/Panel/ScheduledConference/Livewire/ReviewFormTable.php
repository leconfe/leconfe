<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\Topics\TopicCreateAction;
use App\Actions\Topics\TopicUpdateAction;
use App\Models\ReviewForm;
use App\Models\Topic;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component as FormComponent;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
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
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Date;
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
			->query(ReviewForm::query()->ordered())
			->reorderable('order_column')
			->columns([
				TextColumn::make('label')
					// ->description(fn(ReviewForm $record) => $record->getMeta("description"))
					->wrap()
					->searchable(),
				TextColumn::make('weight')
					->getStateUsing(fn(ReviewForm $record) => $record->isEnableScoring() ? $record->weight : '-')
					->searchable(),
			])
			->headerActions([
				CreateAction::make()
					->modalWidth(MaxWidth::ExtraLarge)
					->form(fn(Form $form) => $this->form($form))
					->using(function ($data) {
						try {
							DB::beginTransaction();

							$record = ReviewForm::create($data);
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
					->form(fn(Form $form) => $this->form($form))
					->mutateRecordDataUsing(function (ReviewForm $record, array $data) {
						$data['meta'] = $record->getAllMeta()->toArray();

						return $data;
					})
					->using(function (ReviewForm $record, array $data) {
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
						->modalWidth(MaxWidth::ExtraLarge)
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

								$record = ReviewForm::create($data);
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
					->required(),
				Textarea::make('meta.description')
					->autosize(),
				Checkbox::make('meta.required')
					->label('Reviewers required to complete item'),
				Select::make('type')
					->required()
					->live()
					->options(fn() => ReviewForm::getOptions())
					->rule(fn(): Closure => function (string $attribute, $value, Closure $fail) {
						if (! array_key_exists($value, ReviewForm::getOptions())) {
							$fail('Option unavailable');
						}
					})
					->label('Item Type'),
				$this->getSchemaTypeSelect(),
				$this->getSchemaTypeCheckbox(),
				$this->getSchemaTypeRadio(),
			]);
	}

	protected function getSchemaTypeSelect(): FormComponent
	{
		return Grid::make(1)
			->visible(fn(Get $get) => $get('type') == ReviewForm::TYPE_SELECT)
			->schema([
				TextInput::make('weight')
					->hintIcon('heroicon-m-question-mark-circle', 'Enter the weight as a percentage. This determines the contribution of this criterion to the final score.')
					->numeric()
					->rule(fn(?ReviewForm $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
						$currentWeight = ReviewForm::query()
							->when($record, fn($query) => $query->where('id', '!=', $record->getKey()))
							->whereNotNull('weight')
							->sum('weight');

						$totalWeight = $currentWeight + $value;


						if ($totalWeight > 100) {
							$fail('Please ensure the total weight does not exceed 100%. Currently, it is ' . $totalWeight . '%.');
						}
					})
					->maxValue(100)
					->minValue(0)
					->suffix('%'),
				Repeater::make('meta.select_options')
					->label('Response Options')
					->hint('Enter a value between 10 and 1')
					->hintIcon('heroicon-m-question-mark-circle', 'Enter a value between 10 and 1, where 10 represents the highest rating and 1 the lowest. These values define the score used in reviewer selections.')
					->required()
					->columns(4)
					->reorderable()
					->schema([
						TextInput::make('value')
							->integer()
							->minValue(1)
							->maxValue(10)
							->required()
							->distinct(),
						TextInput::make('label')
							->required()
							->columnSpan([
								'lg' => 3
							]),
					])
					->maxItems(10)
			]);
	}

	protected function getSchemaTypeCheckbox(): FormComponent
	{
		return Grid::make(1)
			->visible(fn(Get $get) => $get('type') == ReviewForm::TYPE_CHECKBOX)
			->schema([
				Repeater::make('meta.checkbox_options')
					->label('Response Options')
					->simple(
						TextInput::make('option')
							->required()
					)
			]);
	}

	protected function getSchemaTypeRadio(): FormComponent
	{
		return Grid::make(1)
			->visible(fn(Get $get) => $get('type') == ReviewForm::TYPE_RADIO)
			->schema([
				Repeater::make('meta.radio_options')
					->label('Response Options')
					->simple(
						TextInput::make('option')
							->required()
					)
			]);
	}
}
