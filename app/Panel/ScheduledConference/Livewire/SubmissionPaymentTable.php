<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class SubmissionPaymentTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function mount() {}

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return Payment::query()
            ->type(PaymentManager::TYPE_SUBMISSION_FEE)
            ->with(['model.conference', 'user']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->queryStringIdentifier('submission_payment')
            ->recordUrl(fn (Payment $record) => PaymentDetail::getUrl(['record' => $record]))
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('invoice')
                    ->visible(app()->getCurrentScheduledConference()?->isInvoiceEnabled())
                    ->searchable()
                    ->wrap(),
                TextColumn::make('title')
                    ->label('Submission Title')
                    // ->color('primary')
                    // ->url(fn (Payment $record) => $record->model ? SubmissionResource::getUrl('view', ['record' => $record->model]) : null)
                    ->state(fn (Payment $record) => $record->model?->getMeta('title') ?? '-')
                    ->description(fn (Payment $record) => $record->user->full_name)
                    ->wrap(),
                TextColumn::make('fee.name')
                    ->description(fn (Payment $record) => $record->amount ? $record->getFormattedFee() : 0)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Registered at')
                    ->sortable()
                    ->toggleable()
                    ->date(),
                TextColumn::make('paid_at')
                    ->date()
                    ->toggleable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('payment_fee_id')
                    ->label('Payment Fee')
                    ->options(fn () => PaymentFee::query()
                        ->type(PaymentManager::TYPE_SUBMISSION_FEE)
                        ->pluck('name', 'id')),
                TernaryFilter::make('paid_at')
                    ->label('Paid')
                    ->nullable(),
            ]);
    }
}
