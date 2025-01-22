<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Facades\Hook;
use App\Forms\Form;
use App\Frontend\Website\Pages\Page;
use App\Managers\PaymentManager;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFeeFormItem;
use Awcodes\Shout\Components\Shout;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class ParticipantForm extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions;

    protected static string $view = 'frontend.scheduledConference.pages.participant-form';

    protected static string $layout = 'filament-panels::components.layout.base';

    public PaymentFee $paymentFee;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(PaymentFee $paymentFee)
    {
        if ($paymentFee->type !== PaymentManager::TYPE_PARTICIPANT_FEE) {
            abort('403', 'Invalid payment fee type');
        }

        $this->form->fill([]);
    }

    public function getTitle(): string|Htmlable
    {
        return "Participant Registration";
    }

    public static function getLayout(): string
    {
        return static::$layout;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        $paymentManager = PaymentManager::get();

        return $form
            ->id('paymentForm')
            ->statePath('data')
            ->model(Participant::class)
            ->schema([
                Placeholder::make('name')
                    ->content($this->paymentFee->name),
                Placeholder::make('type')
                    ->content($this->paymentFee->getPaymentType()),
                Placeholder::make('amount')
                    ->content($this->paymentFee->getFormattedFee())
                    ->extraAttributes([
                        'style' => 'font-size:1rem;',
                    ]),
                Placeholder::make('description')
                    ->content($this->paymentFee->getMeta('description'))
                    ->visible($this->paymentFee->getMeta('description') ?? false),
                Grid::make()
                    ->schema([
                        TextInput::make('given_name')
                            ->label(__('general.given_name'))
                            ->required(),
                        TextInput::make('family_name')
                            ->label(__('general.family_name')),
                    ]),
                TextInput::make('public_name')
                    ->label(__('general.public_name')),
                TextInput::make('email')
                    ->email()
                    ->label(__('general.email'))
                    ->required(),
                ...$this->paymentFee->formItems->map(fn(PaymentFeeFormItem $item) => $item->getFormField())->toArray(),
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
            DB::beginTransaction();

            $participant = new Participant();
            $participant->fill(Arr::only($data, ['given_name', 'family_name', 'email']));
            $participant->save();

            $this->form->model($participant)->saveRelationships();

            $paymentManager = PaymentManager::get();
            $payment = $paymentManager->queue(
                $participant,
                $this->paymentFee,
                auth()->user(),
                PaymentManager::TYPE_PARTICIPANT_FEE,
                $this->paymentFee->name,
                route(ParticipantRegistrationSuccess::getRouteName('scheduledConference'), ['participant' => $participant->uuid]),
                $this->paymentFee->getMeta('description')
            );
            $payment->payment_method = $data['payment_method'];
            $payment->save();

            if ($meta = data_get($data, 'meta')) {
                $payment->setManyMeta($meta);
            }

            $requestUrl = $payment->getMeta('request_url');

            Hook::call('Frontend::Payment::handleRequestUrl', [$payment, &$data, &$requestUrl]);

            DB::commit();

            return redirect()->to($requestUrl);
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get("/{$slug}/{paymentFee}", static::class)
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
