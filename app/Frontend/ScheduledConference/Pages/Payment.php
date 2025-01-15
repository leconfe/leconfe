<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Facades\Hook;
use App\Frontend\Website\Pages\Page;
use App\Models\PaymentQueue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Payment extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.payment';

    public PaymentQueue $paymentQueue;

    public function mount(PaymentQueue $paymentQueue)
    {
        if($paymentQueue->isExpired()){
            abort('403', 'Payment Queue is expired');
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('general.payment');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.payment'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $currentScheduledConference = app()->getCurrentScheduledConference();

        return [
            'paymentDetails' => $this->paymentMethod(),
            'paymentQueue' => $this->paymentQueue,
            'paymentInformation' => $currentScheduledConference->getMeta('payment_information'),
        ];
    }

    protected function paymentMethod()
    {
        $paymentMethods = [];
        
        Hook::call('Frontend::Payment::getPaymentMethod', [$this->paymentQueue, &$paymentMethods]);

        return $paymentMethods;
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get("/{$slug}/{paymentQueue}", static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
