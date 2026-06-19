<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Infolists\Infolist;
use App\Managers\PaymentManager;
use App\Models\Enums\SubmissionStatus;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Panel\ScheduledConference\Livewire\SubmissionPaymentTable;
use App\Panel\ScheduledConference\Pages\Payments;
use App\Panel\ScheduledConference\Pages\ScheduledConferenceSetting;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Squire\Models\Currency;

class Overview extends Widget implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'panel.scheduledConference.widgets.overview';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user->can('viewDashboardOverview', app()->getCurrentScheduledConference());
    }

    public static function getSubmissionPaymentOverviewState(): string
    {
        $submissionPaymentCount = static::getValidSubmissionPaymentQuery()->count();

        $paidSubmissionPaymentCount = static::getValidSubmissionPaymentQuery()
            ->whereNotNull('paid_at')
            ->count();

        return $paidSubmissionPaymentCount.' / '.$submissionPaymentCount;
    }

    protected static function getValidSubmissionPaymentQuery(): Builder
    {
        return Payment::query()
            ->type(PaymentManager::TYPE_SUBMISSION_FEE)
            ->whereHasMorph(
                'model',
                [Submission::class],
                fn (Builder $query) => $query->whereIn('status', SubmissionPaymentTable::getValidSubmissionStatusValues()),
            );
    }

    public function scheduledConferenceInfolist(Infolist $infolist): Infolist
    {
        $currencies = PaymentFee::query()
            ->distinct('currency')
            ->pluck('currency')
            ->mapWithKeys(fn ($currency, $key) => [$currency => Payment::query()->where('currency', $currency)->whereNotNull('paid_at')->sum('amount')]);

        return $infolist
            ->record(app()->getCurrentScheduledConference())
            ->columns(2)
            ->schema([
                Section::make('Overview')
                    ->columnSpanFull()
                    ->headerActions([
                        Action::make('setting')
                            ->url(ScheduledConferenceSetting::getUrl()),
                        Action::make('publish')
                            ->color('success')
                            ->hidden(fn (ScheduledConference $record) => auth()->user()->can('update', $record) && $record->is_published)
                            ->requiresConfirmation()
                            ->successNotificationTitle('Scheduled Conference Published')
                            ->action(function (ScheduledConference $record, array $data, Action $action) {
                                ScheduledConferenceUpdateAction::run($record, ['is_published' => true]);

                                return $action->success();
                            }),
                        Action::make('set_as_draft')
                            ->color('warning')
                            ->hidden(fn (ScheduledConference $record) => auth()->user()->can('update', $record) && ! $record->is_published)
                            ->requiresConfirmation()
                            ->successNotificationTitle('Scheduled Conference Drafted')
                            ->action(function (ScheduledConference $record, array $data, Action $action) {
                                ScheduledConferenceUpdateAction::run($record, ['is_published' => false]);

                                return $action->success();
                            }),
                    ])
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')
                            ->icon('heroicon-m-building-library'),
                        TextEntry::make('full_date')
                            ->visible(fn (ScheduledConference $record) => filled($record->date_start))
                            ->icon('heroicon-m-calendar-days'),
                        TextEntry::make('status')
                            ->getStateUsing(fn ($record) => match ($record->is_published) {
                                true => __('general.published'),
                                false => __('general.draft'),
                            })
                            ->color(fn ($record) => match ($record->is_published) {
                                true => 'success',
                                false => 'gray',
                            })
                            ->badge(),
                        TextEntry::make('coordinator')
                            ->icon('heroicon-m-user-group')
                            ->visible(fn (ScheduledConference $record) => filled($record->getMeta('coordinator')))
                            ->getStateUsing(fn (ScheduledConference $record) => $record->getMeta('coordinator')),
                        TextEntry::make('location')
                            ->icon('heroicon-m-map-pin')
                            ->visible(fn (ScheduledConference $record) => filled($record->getMeta('location')))
                            ->getStateUsing(fn (ScheduledConference $record) => $record->getMeta('location')),
                    ]),
                Section::make('Submissions')
                    ->columnSpan(1)
                    ->columns(2)
                    ->headerActions([
                        Action::make('open_submissions')
                            ->color('gray')
                            ->url(SubmissionResource::getUrl('index', ['activeTab' => 1])),
                    ])
                    ->schema([
                        TextEntry::make('submitted')
                            ->getStateUsing(
                                fn (ScheduledConference $record) => $record->submittedSubmissions()->count()
                            )
                            ->size(TextEntrySize::Large)
                            ->color('primary')
                            ->url(SubmissionResource::getUrl('index')),
                        TextEntry::make('unassigned')
                            ->getStateUsing(
                                fn () => Submission::query()->doesntHave('editors')->whereNotIn('status', [
                                    SubmissionStatus::Incomplete,
                                    SubmissionStatus::Published,
                                    SubmissionStatus::Withdrawn,
                                ])->count()
                            )
                            ->size(TextEntrySize::Large)
                            ->color('primary')
                            ->url(SubmissionResource::getUrl('index', ['activeTab' => 1])),
                        TextEntry::make('reviews')
                            ->getStateUsing(
                                fn () => Submission::query()->where('status', SubmissionStatus::OnReview)->count()
                            )
                            ->size(TextEntrySize::Large)
                            ->color('primary')
                            ->url(SubmissionResource::getUrl('index', ['activeTab' => 2, 'tableFilters[status][value]' => SubmissionStatus::OnReview->value])),
                        TextEntry::make('published')
                            ->getStateUsing(
                                fn () => Submission::query()->where('status', SubmissionStatus::Published)->count()
                            )
                            ->size(TextEntrySize::Large)
                            ->color('primary')
                            ->url(SubmissionResource::getUrl('index', ['activeTab' => 3, 'tableFilters[status][value]' => SubmissionStatus::Published->value])),
                    ]),
                Section::make('Payments')
                    ->columnSpan(1)
                    ->columns(2)
                    ->headerActions([
                        Action::make('open_payments')
                            ->color('gray')
                            ->url(Payments::getUrl()),
                    ])
                    ->schema([
                        TextEntry::make('submission_payment')
                            ->label('Submission Payment')
                            ->size(TextEntrySize::Large)
                            ->getStateUsing(fn () => static::getSubmissionPaymentOverviewState()),
                        TextEntry::make('participant_payment')
                            ->label('Participant Payment')
                            ->size(TextEntrySize::Large)
                            ->getStateUsing(function () {
                                $submissionPaymentCount = Payment::query()
                                    ->type(PaymentManager::TYPE_PARTICIPANT_FEE)
                                    ->count();

                                $paidSubmissionPaymentCount = Payment::query()
                                    ->type(PaymentManager::TYPE_PARTICIPANT_FEE)
                                    ->whereNotNull('paid_at')
                                    ->count();

                                return $paidSubmissionPaymentCount.' / '.$submissionPaymentCount;
                            }),
                        ...$currencies->map(function ($total, $code) {
                            $currency = Currency::find($code);

                            if (! $currency) {
                                return null;
                            }

                            return TextEntry::make('paid_'.$currency)
                                ->label('Paid ('.$currency->name.')')
                                ->size(TextEntrySize::Large)
                                ->state(money($total, $code, true)->formatWithoutZeroes());
                        })->filter(),
                    ]),
            ]);
    }
}
