<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Hook;
use App\Managers\PaymentManager;
use App\Models\Enums\UserRole;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Notifications\ParticipantPayment;
use App\Notifications\ParticipantRegistered;
use App\Services\Notifications\OperationalNotificationRecipients;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Validation\ValidationException;
use Squire\Models\Country;

class ParticipantRegistration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'panel.scheduledConference.pages.participant-register';

    protected static ?int $navigationSort = 99;

    public ?array $formData = [];

    // Cached per request; protected properties are not persisted by Livewire.
    protected ?bool $availableParticipantFeesExist = null;

    protected ?bool $configuredParticipantFeesExist = null;

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
        return app()->getCurrentScheduledConference()->isParticipantRegistrationEnabled() && ! auth()->user()?->isRegisteredAsParticipant() && auth()->user()?->can('registerParticipant', Payment::class) && auth()->user()?->hasRole(UserRole::Participant);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $isOpen = true;
        $closedMessage = __('general.registration_closed');

        if ($this->hasConfiguredParticipantFees() && ! $this->hasAvailableParticipantFees()) {
            $isOpen = false;
            $closedMessage = __('participant_registration.fees_unavailable');
        }

        // Plugins get the final say on availability, including when no fee is available.
        Hook::call('Panel::ScheduledConference::ParticipantRegistration::availability', [&$isOpen, &$closedMessage, $this]);

        return [
            'coverImageUrl' => app()->getCurrentScheduledConference()->getFirstMediaUrl('registration_cover'),
            'registrationFormHeader' => app()->getCurrentScheduledConference()->getMeta('registration_form_header') ? new HtmlString(app()->getCurrentScheduledConference()->getMeta('registration_form_header')) : null,
            'isOpen' => (bool) $isOpen,
            'closedMessage' => $closedMessage,
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
                        Placeholder::make('registration_fee_notice')
                            ->hiddenLabel()
                            ->content(fn () => $this->hasConfiguredParticipantFees()
                                ? __('participant_registration.fees_unavailable')
                                : __('participant_registration.free_notice'))
                            ->visible(fn () => ! $this->hasAvailableParticipantFees()),
                        Radio::make('payment_fee_id')
                            ->label(__('participant_registration.payment_fee'))
                            ->required(fn () => $this->hasConfiguredParticipantFees())
                            ->visible(fn () => $this->hasAvailableParticipantFees())
                            ->live()
                            ->options(
                                fn () => $this->availableParticipantFees()
                                    ->with('translate')
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => $paymentFee->translated('name')])
                            )
                            ->descriptions(
                                fn () => $this->availableParticipantFees()
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => '('.$paymentFee->getFormattedFee().')'])
                            ),
                        \Filament\Forms\Components\Fieldset::make('Add-on Items')
                            ->schema(function (Get $get) {
                                $paymentFee = PaymentFee::find($get('payment_fee_id'));
                                if (! $paymentFee) {
                                    return [];
                                }

                                return collect($paymentFee->getTranslatedAdditionalItems())->map(function ($item) use ($paymentFee) {
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
        $scheduledConference = app()->getCurrentScheduledConference()?->fresh();

        if (! $scheduledConference?->isParticipantRegistrationEnabled()) {
            Notification::make()
                ->warning()
                ->title(__('general.registration_closed'))
                ->send();

            return null;
        }

        // Read before getState(): the fee field is hidden once no fee is available, which drops its value.
        $paymentFeeId = data_get($this->formData, 'payment_fee_id');
        $data = $this->form->getState();
        $paymentFormResponses = PaymentFormItem::filterOutUploadResponses(data_get($data, 'form_responses'));

        try {
            DB::beginTransaction();

            $currentUser = auth()->user();
            $currentUser->newQuery()->whereKey($currentUser->id)->lockForUpdate()->firstOrFail();
            if (Participant::where('scheduled_conference_id', $scheduledConference->id)->where('email', $currentUser->email)->exists()) {
                throw ValidationException::withMessages(['formData.given_name' => __('participant_registration.already_registered')]);
            }

            $participant = new Participant;
            $participant->fill(Arr::only($data, ['given_name', 'family_name']));
            $participant->email = $currentUser->email;
            $participant->save();

            $meta = data_get($data, 'meta');

            $participant->setManyMeta($meta);
            $currentUser->setManyMeta($meta);

            $paymentFee = $this->availableParticipantFees()->find($paymentFeeId);
            // A fee that was selected but disappeared must never fall back to a free registration.
            if (! $paymentFee && (filled($paymentFeeId) || $this->hasConfiguredParticipantFees())) {
                throw ValidationException::withMessages([
                    'formData.payment_fee_id' => __('participant_registration.fee_changed'),
                ]);
            }
            if (! $paymentFee) {
                // Keep the existing response/upload owner and participant list, without a charge.
                $payment = Payment::create([
                    'user_id' => $currentUser->id,
                    'type' => PaymentManager::TYPE_PARTICIPANT_FEE,
                    'model_type' => Participant::class,
                    'model_id' => $participant->id,
                    'payment_fee_id' => null,
                    'amount' => 0,
                    'currency' => 'XXX',
                    'payment_method' => 'free_registration',
                ]);
                $payment->setManyMeta(['title' => __('participant_registration.free'), 'base_amount' => 0]);
            } else {
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
            }

            if (array_key_exists('form_responses', $data)) {
                $payment->setMeta('form_responses', $paymentFormResponses);
            }

            $this->form->model($payment)->saveRelationships();

            if (! $payment->isFreeRegistration() && app()->getCurrentScheduledConference()->isParticipantPaymentAutoNotify()) {
                $payment->ensureInvoice();
                auth()->user()->notify(
                    (new ParticipantPayment($payment->getKey()))->afterCommit()
                );
                $payment->markInvoiceAsSent();
            }

            app(OperationalNotificationRecipients::class)
                ->forRoles([UserRole::ConferenceManager])
                ->each(fn ($user) => $user->notify(new ParticipantRegistered($participant)));

            DB::commit();
        } catch (\Throwable $th) {
            // Validation errors are already shown on the form field.
            if (! $th instanceof ValidationException) {
                Notification::make()
                    ->danger()
                    ->title($th->getMessage())
                    ->send();
            }

            DB::rollBack();
            throw $th;
        }

        Notification::make()
            ->success()
            ->title(__('general.saved'))
            ->send();

        redirect()->to(PaymentDetail::getUrl(['record' => $payment]));
    }

    protected function availableParticipantFees(): \Illuminate\Database\Eloquent\Builder
    {
        return PaymentFee::query()
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->type(PaymentManager::TYPE_PARTICIPANT_FEE)->active();
    }

    protected function hasAvailableParticipantFees(): bool
    {
        return $this->availableParticipantFeesExist ??= $this->availableParticipantFees()->exists();
    }

    protected function hasConfiguredParticipantFees(): bool
    {
        return $this->configuredParticipantFeesExist ??= PaymentFee::query()
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->type(PaymentManager::TYPE_PARTICIPANT_FEE)->exists();
    }
}
