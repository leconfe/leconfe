<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Facades\Hook;
use App\Forms\Form;
use App\Frontend\Website\Pages\Page;
use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Models\PaymentFeeFormItem;
use Awcodes\Shout\Components\Shout;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class PaymentForm extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static string $view = 'frontend.scheduledConference.pages.payment-form';

    protected static string $layout = 'filament-panels::components.layout.base';

    public Payment $payment;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(Payment $payment)
    {
        // dd($payment, $payment->getAllMeta());
        if ($payment->isExpired()) {
            $payment->delete();

            abort('403', 'Payment is expired');
        }

        if($payment->isPaid()){
            abort('403', 'Payment fee already paid');
        }

        $this->form->fill([
            ...$this->payment->attributesToArray(),
            'meta' => $this->payment->getAllMeta()->toArray(),
        ]);


    }

    public function getTitle(): string|Htmlable
    {
        return __('general.payment');
    }

    public static function getLayout(): string
    {
        return static::$layout;
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
            'heading' => "Pay",
            'paymentDetails' => $this->paymentMethod(),
            'payment' => $this->payment,
            'paymentInformation' => $currentScheduledConference->getMeta('payment_information'),
        ];
    }

    public function form(Form $form): Form
    {
        $paymentManager = PaymentManager::get();
        
        return $form
            ->id('paymentConfirmation')
            ->statePath('data')
            ->model($this->payment)
            ->schema([
                Shout::make('policy')
                    ->icon(fn() => null)
                    ->content(fn() => new HtmlString(app()->getCurrentScheduledConference()?->getMeta('submission_payment_policy')))
                    ->visible(app()->getCurrentScheduledConference()?->getMeta('submission_payment_policy') ?? false),
                Placeholder::make('title')
                    ->content($this->payment->getMeta('title')),
                Placeholder::make('type')
                    ->content($this->payment->getPaymentType()),
                Placeholder::make('amount')
                    ->content($this->payment->getFormattedFee())
                    ->extraAttributes([
                        'style' => 'font-size:1rem;',
                    ]),
                Placeholder::make('description')
                    ->content($this->payment->getMeta('description'))
                    ->visible($this->payment->getMeta('description') ?? false),
                ...$this->payment?->fee?->formItems?->map(fn(PaymentFeeFormItem $item) => $item->getFormField())->toArray(),
                Radio::make('payment_method')
                    ->required()
                    ->reactive()
                    ->options($paymentManager->getPaymentMethodOptions())
            ]);
    }

    public function submitAction()
    {
        return Action::make('submitAction')
            ->label('Submit')
            ->submit('submit')
            ->button();
    }

    public function submit()
    {
        $data = $this->form->getState();

        try {
            $this->payment->update($data);

            $this->form->model($this->payment)->saveRelationships();

            if($meta = data_get($data, 'meta')){
                $this->payment->setManyMeta($meta);
            }

            $requestUrl = $this->payment->getMeta('request_url');

            Hook::call('Frontend::PaymentForm::submit', [$this->payment, &$data, &$requestUrl]);

            return redirect()->to($requestUrl);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    protected function paymentMethod()
    {
        $paymentMethods = [];

        Hook::call('Frontend::PaymentForm::getPaymentMethod', [$this->payment, &$paymentMethods]);

        return $paymentMethods;
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get("/{$slug}/{payment}", static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }

    /**
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [static::class];
    }

    /**
     * @return array<mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        return [];
    }
}
