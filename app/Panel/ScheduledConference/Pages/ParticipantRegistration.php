<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Managers\PaymentManager;
use App\Models\Enums\UserRole;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Models\User;
use App\Notifications\ParticipantPayment;
use App\Notifications\ParticipantRegistered;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Squire\Models\Country;

class ParticipantRegistration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'panel.scheduledConference.pages.participant-register';

    protected static ?int $navigationSort = 99;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'given_name' => auth()->user()?->given_name,
            'family_name' => auth()->user()?->family_name,
            'email' => auth()->user()?->email,
            'affiliation' => auth()->user()?->getMeta('affiliation'),
        ]);
    }

    public static function canAccess(): bool
    {
        return app()->getCurrentScheduledConference()->isParticipantRegistrationEnabled() && auth()->user()->can('registerParticipant', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'coverImageUrl' => app()->getCurrentScheduledConference()->getFirstMediaUrl('registration_cover'),
            'registrationFormHeader' => app()->getCurrentScheduledConference()->getMeta('registration_form_header') ? new HtmlString(app()->getCurrentScheduledConference()->getMeta('registration_form_header')) : null,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->operation('create')
            ->schema([
                Section::make()
                    ->columns(1)
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('given_name')
                                    ->label(__('general.given_name'))
                                    ->required(),
                                TextInput::make('family_name')
                                    ->label(__('general.family_name')),
                            ]),
                        TextInput::make('email')
                            ->email()
                            ->label(__('general.email'))
                            ->disabled(),
                        TextInput::make('meta.affiliation')
                            ->label('Affiliation'),
                        TextInput::make('meta.address_line')
                            ->label('Address Line'),
                        TextInput::make('meta.post_code')
                            ->label('Postcode / ZIP Code'),
                        TextInput::make('meta.city')
                            ->label('City'),
                        Select::make('meta.country')
                            ->label('Country')
                            ->searchable()
                            ->options(fn () => Country::all()->mapWithKeys(fn ($country) => [$country->id => $country->flag.' '.$country->name]))
                            ->optionsLimit(250),
                        ...PaymentFormItem::buildFormSchema(PaymentManager::TYPE_PARTICIPANT_FEE),
                        Radio::make('payment_fee_id')
                            ->label('Payment Fee')
                            ->required()
                            ->live()
                            ->options(
                                fn () => PaymentFee::type(PaymentManager::TYPE_PARTICIPANT_FEE)
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => $paymentFee->name])
                            )
                            ->descriptions(
                                fn () => PaymentFee::type(PaymentManager::TYPE_PARTICIPANT_FEE)
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => '('.$paymentFee->getFormattedFee().')'])
                            ),
                        \Filament\Forms\Components\Fieldset::make('Add-on Items')
                            ->schema(function (Get $get) {
                                $paymentFee = PaymentFee::find($get('payment_fee_id'));
                                if (! $paymentFee) {
                                    return [];
                                }

                                return collect($paymentFee->getAdditionalItems())->map(function ($item) use ($paymentFee) {
                                    $formattedAmount = money($item['amount'], $paymentFee->currency, true)->formatWithoutZeroes();

                                    return \App\Forms\Components\AddOnItemCounter::make("additional_items.{$item['key']}")
                                        ->label("{$item['name']} ({$formattedAmount})")
                                        ->helperText($item['description'] ?? null)
                                        ->minValue(0)
                                        ->maxValue(999);
                                })->toArray();
                            })
                            ->columns(1)
                            ->visible(function (Get $get) {
                                $paymentFee = PaymentFee::find($get('payment_fee_id'));

                                return $paymentFee && count($paymentFee->getAdditionalItems()) > 0;
                            }),
                    ]),
            ])
            ->statePath('formData');
    }

    public function submit()
    {
        $data = $this->form->getState();
        $paymentFormResponses = PaymentFormItem::filterOutUploadResponses(data_get($data, 'form_responses'));

        try {
            DB::beginTransaction();

            $currentUser = auth()->user();

            $participant = new Participant;
            $participant->fill(Arr::only($data, ['given_name', 'family_name']));
            $participant->email = $currentUser->email;
            $participant->save();

            $meta = data_get($data, 'meta');

            $participant->setManyMeta($meta);
            $currentUser->setManyMeta($meta);

            $paymentFee = PaymentFee::find($data['payment_fee_id']);
            $additionalItems = data_get($data, 'additional_items', []);
            $selectedAdditionalItems = $paymentFee->getSelectedAdditionalItemsFromData(['additional_items' => $additionalItems]);
            $totalAmount = $paymentFee->getAmountWithAdditionalItemsFromData(['additional_items' => $additionalItems]);
            $paymentManager = PaymentManager::get();
            $payment = $paymentManager->queue(
                $participant,
                $paymentFee,
                auth()->user(),
                PaymentManager::TYPE_PARTICIPANT_FEE,
                $paymentFee->name,
                Dashboard::getUrl(),
                $paymentFee->getMeta('description'),
                $totalAmount,
                $paymentFee->currency,
                null,
                $selectedAdditionalItems,
                $paymentFee->amount,
            );

            $payment->save();

            if (array_key_exists('form_responses', $data)) {
                $payment->setMeta('form_responses', $paymentFormResponses);
            }

            $this->form->model($payment)->saveRelationships();

            auth()->user()->notify(new ParticipantPayment($participant));

            User::role([UserRole::Admin->value, UserRole::ConferenceManager->value])
                ->lazy()
                ->each(fn ($user) => $user->notify(new ParticipantRegistered($participant)));

            DB::commit();
        } catch (\Throwable $th) {
            Notification::make()
                ->danger()
                ->title($th->getMessage())
                ->send();

            DB::rollBack();
            throw $th;
        }

        Notification::make()
            ->success()
            ->title(__('general.saved'))
            ->send();

        redirect()->to(PaymentDetail::getUrl(['record' => $payment]));
    }
}
