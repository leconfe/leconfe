<?php

namespace App\Utils\UpgradeSchemas;

use App\Frontend\ScheduledConference\Pages\ParticipantRegistrationSuccess;
use App\Managers\PaymentManager;
use App\Models\NavigationMenuItem;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\Permission;
use App\Models\RegistrationType;
use App\Models\Review;
use App\Models\Track;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Upgrade120Beta4 extends UpgradeBase
{
    public function run(): void
    {
        $this->migrate();

        $this->migrateRegistrationPayments();

        $this->removeNavigationItem();
    }

    protected function migrate(): void
    {
        Artisan::call('migrate');
    }

    public function migrateRegistrationPayments(): void
    {
        try {
            DB::beginTransaction();

            RegistrationType::query()
                ->with(['meta', 'scheduledConference.conference', 'registration' => fn($query) => $query->with(['user', 'registrationPayment'])])
                ->get()
                ->each(function ($registrationType) {
                    $paymentFee = new PaymentFee([
                        'name' => $registrationType->type,
                        'type' => match ($registrationType->level) {
                            RegistrationType::LEVEL_AUTHOR => PaymentManager::TYPE_SUBMISSION_FEE,
                            RegistrationType::LEVEL_PARTICIPANT => PaymentManager::TYPE_PARTICIPANT_FEE,
                            default => PaymentManager::TYPE_PARTICIPANT_FEE,
                        },
                        'amount' => $registrationType->cost,
                        'currency' => $registrationType->currency,
                        'opened_at' => $registrationType->opened_at,
                        'closed_at' => $registrationType->closed_at,
                        'order_column' => $registrationType->order_column,
                        'limit' => $registrationType->quota,
                        'is_active' => $registrationType->active,
                        'scheduled_conference_id' => $registrationType->scheduled_conference_id,
                        'conference_id' => $registrationType->scheduledConference->conference_id,
                    ]);

                    $paymentFee->save();

                    $paymentFee->setMeta('description', $registrationType->getMeta('description'));

                    if (RegistrationType::LEVEL_PARTICIPANT) {
                        foreach ($registrationType->registration as $registration) {
                            $participant = new Participant([
                                'given_name' => $registration->user->given_name,
                                'family_name' => $registration->user->family_name,
                                'public_name' => $registration->user->full_name,
                                'email' => $registration->user->email,
                                'scheduled_conference_id' => $registrationType->scheduled_conference_id,
                                'conference_id' => $registrationType->scheduledConference->conference_id,
                            ]);

                            $participant->save();

                            $payment = new Payment([
                                'user_id' => $registration->user?->getKey(),
                                'type' => $paymentFee->type,
                                'model_type' => Participant::class,
                                'model_id' => $participant->getKey(),
                                'payment_fee_id' => $paymentFee->getKey(),
                                'expired_at' => null,
                                'amount' => $registration->registrationPayment->cost,
                                'currency' => $registration->registrationPayment->currency,
                                'scheduled_conference_id' => $registrationType->scheduled_conference_id,
                                'conference_id' => $registrationType->scheduledConference->conference_id,
                            ]);

                            if ($registration->registrationPayment->paid_at) {
                                $payment->payment_method = "manual";
                                $payment->paid_at = $registration->registrationPayment->paid_at;
                            }

                            $payment->save();

                            $payment->setManyMeta([
                                'title' => $paymentFee->name,
                                'request_url' => route(ParticipantRegistrationSuccess::getRouteName('scheduledConference'), [
                                    'participant' => $participant->uuid,
                                    'serie' => $registrationType->scheduledConference->path,
                                    'conference' => $registrationType->scheduledConference->conference->path,
                                ]),
                                'description' => $paymentFee->getMeta('description'),
                            ]);
                        }
                    }
                });

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function removeNavigationItem()
    {
        NavigationMenuItem::whereIn('type', ['agenda', 'participant-registration'])->delete();
    }
}
