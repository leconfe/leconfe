<?php

namespace App\Managers;

use App\Facades\Hook;
use App\Interfaces\HasPayment;
use App\Models\Payment;
use App\Models\PaymentCompleted;
use App\Models\PaymentFee;
use App\Models\PaymentQueue;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Lottery;

class PaymentManager
{
	public const TYPE_SUBMISSION_FEE = 1;
	public const TYPE_ATTENDANCE_FEE = 2;

	public static function get(): PaymentManager
	{
		return app(self::class);
	}

	public function queue(
		Model & HasPayment $model,
		PaymentFee $paymentFee,
		User $user,
		int $type,
		string $title,
		?string $description = null,
		float $amount = null,
		?string $currency = null,
		Carbon $expiredAt = null,
	) {
		
		$paymentQueue = new Payment([
			'user_id' => $user->getKey(),
			'type' => $type,
			'model_type' => $model::class,
			'model_id' => $model->getKey(),
			'payment_fee_id' => $paymentFee->getKey(),
			'expired_at' => $expiredAt,
			'amount' => $amount ?? $paymentFee->amount,
			'currency' => $currency ?? $paymentFee->currency,
		]);

		$paymentQueue->save();

		$requestUrl = match ($type) {
			self::TYPE_SUBMISSION_FEE => SubmissionResource::getUrl('view', ['record' => $model]),
			self::TYPE_ATTENDANCE_FEE => "", //TODO add link for attendance
			default => throw new Exception('Invalid payment type'),
		};

		$paymentQueue->setManyMeta([
			'title' => $title,
			'user_id' => $user->getKey(),
			'request_url' => $requestUrl,
			'description' => $description,
		]);

		Lottery::odds(1, 20)->winner(fn() => Payment::deleteExpired());

		return $paymentQueue;
	}

	public function getPaymentTypeName(int $type)
	{
		return match ($type) {
			self::TYPE_SUBMISSION_FEE => "Submission Fee",
			self::TYPE_ATTENDANCE_FEE => "Attendance Fee",
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

}
