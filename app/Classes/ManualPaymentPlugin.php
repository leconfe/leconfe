<?php

namespace App\Classes;

use App\Facades\Hook;
use Filament\Forms\Components\ColorPicker;
use Illuminate\Support\Facades\Blade;
use luizbills\CSS_Generator\Generator as CSSGenerator;
use matthieumastadenis\couleur\ColorFactory;
use matthieumastadenis\couleur\ColorSpace;

class ManualPaymentPlugin extends Plugin
{
    public function __construct()
    {
        $this->pluginPath = __DIR__;
    }

    public function boot()
    {
        if(app()->getCurrentScheduledConference()?->getMeta('manual_payment_enabled')){
            Hook::add('Frontend::Payment::getPaymentMethod', function ($hookName, $paymentQueue, &$paymentMethods) {
                $paymentMethods['Manual Payment'] = app()->getCurrentScheduledConference()->getMeta('manual_payment_instructions');
    
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
