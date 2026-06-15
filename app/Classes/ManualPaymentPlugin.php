<?php

namespace App\Classes;

use App\Facades\Hook;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Mail\Templates\UserPayPaymentMail;
use App\Models\Enums\UserRole;
use App\Models\Payment;
use App\Services\Notifications\OperationalNotificationRecipients;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class ManualPaymentPlugin extends Plugin
{
    public function __construct()
    {
        $this->pluginPath = __DIR__;
    }

    public function boot()
    {
        if (app()->getCurrentScheduledConference()?->getMeta('manual_payment_enabled')) {
            Hook::add('PaymentManager::getPaymentMethodActions', function ($hookName, &$actions) {
                $actions['manual'] = Action::make('manual')
                    ->label(app()->getCurrentScheduledConference()->getMeta('manual_payment_name') ?? 'Manual Payment')
                    ->fillForm([])
                    ->form([
                        Placeholder::make('manual_payment_instructions')
                            ->hiddenLabel()
                            ->label('Payment Instructions')
                            ->content(fn () => new HtmlString(app()->getCurrentScheduledConference()->getMeta('manual_payment_instructions')))
                            ->visible(fn () => app()->getCurrentScheduledConference()->getMeta('manual_payment_instructions')),
                        Placeholder::make('amount')
                            ->label('Amount')
                            ->content(fn (Payment $record) => $record->getFormattedFee()),
                        SpatieMediaLibraryFileUpload::make('payment_proof')
                            ->label('Payment Proof')
                            ->required()
                            ->disk('private-files')
                            ->visibility('private')
                            ->collection('manual_payment_proof'),
                    ])
                    ->action(function (Action $action, array $data, Payment $record) {
                        $record->touch();

                        app(OperationalNotificationRecipients::class)
                            ->forRoles([UserRole::ConferenceManager, UserRole::ScheduledConferenceEditor])
                            ->each(fn ($user) => Mail::to($user->email)->send(new UserPayPaymentMail($record)));

                        $action->successNotificationTitle('Submit success.');
                        $action->success();
                    });

                return false;
            });

            Hook::add('PaymentManager::getPaymentMethodInfolist', function ($hookName, &$schemas) {
                $schemas[] = Section::make(app()->getCurrentScheduledConference()->getMeta('manual_payment_name') ?? 'Manual Payment')
                    ->visible(fn ($record) => $record->hasMedia('manual_payment_proof'))
                    ->schema([
                        TextEntry::make('payment_proof')
                            ->state('Download')
                            ->color('primary')
                            ->action(
                                InfolistAction::make('download')->action(fn ($record) => $record->getFirstMedia('manual_payment_proof'))
                            ),
                    ]);

                return false;
            });
        }
    }

    public function loadInformation()
    {
        return [
            'name' => 'Manual Payment',
            'folder' => 'ManualPayment',
            'author' => 'Leconfe',
            'description' => 'Manual Payment Plugin for Leconfe',
            'version' => '1.0.0',
        ];
    }

    public function isHidden(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function canBeDisabled(): bool
    {
        return false;
    }
}
