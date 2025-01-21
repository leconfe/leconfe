<?php

namespace App\Classes;

use App\Facades\Hook;
use App\Forms\Form;
use App\Models\Payment;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
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
            Hook::add('PaymentManager::getPaymentMethodOptions', function ($hookName, &$options) {
                $options['manual'] = app()->getCurrentScheduledConference()->getMeta('manual_payment_name') ?? 'Manual Payment';
                return false;
            });

            Hook::add('Forms::Form::components::paymentForm', function ($hookName, array &$components, Form $form) {

                $components[] = Grid::make(1)
                    ->visible(fn(Get $get) => $get('payment_method') == 'manual')
                    ->schema([
                        Placeholder::make('manual_payment_instructions')
                            ->label('Payment Instructions')
                            ->content(fn() => new HtmlString(app()->getCurrentScheduledConference()->getMeta('manual_payment_instructions')))
                            ->visible(fn() => app()->getCurrentScheduledConference()->getMeta('manual_payment_instructions')),
                        SpatieMediaLibraryFileUpload::make('payment_proof')
                            ->label('Payment Proof')
                            ->required()
                            ->downloadable()
                            ->collection('manual_payment_proof')
                    ]);

                return false;
            });
            Hook::add('Forms::Form::components::submissionPayment', function ($hookName, array &$components, Form $form) {

                $components[] = Grid::make(1)
                    ->visible(fn(Get $get) => $get('payment_method') == 'manual')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('payment_proof')
                            ->label('Payment Proof')
                            ->required()
                            ->downloadable()
                            ->collection('manual_payment_proof')
                    ])
                    ->disabled();

                return false;
            });

            Hook::add('Frontend::Payment::handleRequestUrl', function ($hookName, Payment $payment, array &$data, string &$requestUrl) {

                if ($data['payment_method'] == 'manual') {
                    Notification::make()
                        ->title('Submit successfully')
                        ->success()
                        ->send();
                }


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
