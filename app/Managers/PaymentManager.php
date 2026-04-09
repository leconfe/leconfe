<?php

namespace App\Managers;

use App\Facades\Hook;
use App\Interfaces\HasPayment;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\Submission;
use App\Models\User;
use App\Services\Billing\SubmissionBillingNotifier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Lottery;

class PaymentManager
{
    public const TYPE_PARTICIPANT_FEE = 1;

    public const TYPE_SUBMISSION_FEE = 2;

    public static function get(): PaymentManager
    {
        return app(self::class);
    }

    public function queue(
        Model&HasPayment $model,
        PaymentFee $paymentFee,
        ?User $user,
        int $type,
        string $title,
        string $requestUrl,
        ?string $description = null,
        ?float $amount = null,
        ?string $currency = null,
        ?Carbon $expiredAt = null,
        array $additionalItems = [],
        ?float $baseAmount = null,
    ) {

        $paymentQueue = new Payment([
            'user_id' => $user?->getKey(),
            'type' => $type,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'payment_fee_id' => $paymentFee->getKey(),
            'expired_at' => $expiredAt,
            'amount' => $amount ?? $paymentFee->amount,
            'currency' => $currency ?? $paymentFee->currency,
        ]);

        $paymentQueue->save();

        $paymentQueue->setManyMeta([
            'title' => $title,
            'request_url' => $requestUrl,
            'description' => $description,
            'additional_items' => $additionalItems,
            'base_amount' => $baseAmount ?? $paymentFee->amount,
        ]);

        if ($model instanceof Submission) {
            $submissionId = $model->getKey();

            DB::afterCommit(function () use ($submissionId) {
                $submission = Submission::withoutGlobalScopes()
                    ->with(['payment', 'user', 'scheduledConference'])
                    ->find($submissionId);

                if (! $submission) {
                    return;
                }

                try {
                    app(SubmissionBillingNotifier::class)->maybeNotifyForSubmission($submission);
                } catch (\Throwable $th) {
                    Log::error($th->getMessage());
                }
            });
        }

        Lottery::odds(1, 20)->winner(fn () => Payment::deleteExpired());

        return $paymentQueue;
    }

    public function getPaymentTypeName(int $type)
    {
        return match ($type) {
            self::TYPE_SUBMISSION_FEE => 'Submission Fee',
            self::TYPE_PARTICIPANT_FEE => 'Participant Fee',
            default => null,
        };
    }

    public function fulfillQueued(Payment $payment, string $paymentMethod, ?int $userId = null)
    {
        $payment->update([
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
        ]);

        $payment->setMeta('paid_by', $userId);

        return true;
    }

    public function getPaymentMethodOptions()
    {
        $options = [];

        Hook::call('PaymentManager::getPaymentMethodOptions', [&$options]);

        return $options;
    }

    public function getPaymentMethodActions()
    {
        $actions = [];

        Hook::call('PaymentManager::getPaymentMethodActions', [&$actions]);

        return $actions;
    }

    public function getPaymentMethodInfolist()
    {
        $schemas = [];

        Hook::call('PaymentManager::getPaymentMethodInfolist', [&$schemas]);

        return $schemas;
    }
}
