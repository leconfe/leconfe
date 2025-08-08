<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Managers\PaymentManager;
use App\Models\Participant;
use App\Models\PaymentFee;
use App\Notifications\ParticipantPayment;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
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
        return app()->getCurrentScheduledConference()->isParticipantRegistrationEnabled() && ! auth()->user()?->isRegisteredAsParticipant();
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
                        Radio::make('payment_fee_id')
                            ->label('Payment Fee')
                            ->visible(fn () => app()->getCurrentScheduledConference()->getMeta('submission_payment'))
                            ->required()
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
                    ]),
            ])
            ->statePath('formData');
    }

    public function submit()
    {
        $data = $this->form->getState();
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
            $paymentManager = PaymentManager::get();
            $payment = $paymentManager->queue(
                $participant,
                $paymentFee,
                auth()->user(),
                PaymentManager::TYPE_PARTICIPANT_FEE,
                $paymentFee->name,
                Dashboard::getUrl(),
                $paymentFee->getMeta('description')
            );

            $payment->save();

            $this->form->model($payment)->saveRelationships();

            auth()->user()->notify(new ParticipantPayment($participant));


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
