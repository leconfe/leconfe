<?php

namespace App\Managers;

use App\Models\PaymentCompleted;
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

	public function queue(string $title, int $type, User $user, Model $model, float $amount, string $currency, ?string $description = null, Carbon $expiredAt = null)
	{
		$paymentQueue = new PaymentQueue([
			'type' => $type,
			'model_type' => $model::class,
			'model_id' => $model->getKey(),
			'expired_at' => $expiredAt,
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
			'amount' => $amount,
			'currency' => $currency,
			'request_url' => $requestUrl,
			'description' => $description,
		]);

		Lottery::odds(1, 20)->winner(fn() => PaymentQueue::deleteExpired());

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

	public function fulfillQueued(PaymentQueue $paymentQueue, string $paymentMethod, ?int $userId = null)
	{
		$paymentCompleted = new PaymentCompleted([
			'type' => $paymentQueue->type,
			'model_type' => $paymentQueue->model_type,
			'model_id' => $paymentQueue->model_id,
			'user_id' => $userId ?? $paymentQueue->getMeta('user_id'),
			'amount' => $paymentQueue->getMeta('amount'),
			'currency' => $paymentQueue->getMeta('currency'),
			'payment_method' => $paymentMethod,
		]);

		$paymentCompleted->save();

		$paymentCompleted->setManyMeta([
			'title' => $paymentQueue->getMeta('title'),
			'description' => $paymentQueue->getMeta('description'),
		]);

		$paymentQueue->delete();

		return true;
	}
}
