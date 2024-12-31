<?php

namespace App\Actions\Review;

use App\Models\Review;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReviewUpdateAction
{
	use AsAction;

	public function handle(Review $record, array $data): Review
	{
		try {
			DB::beginTransaction();

			$record->update($data);

			if (array_key_exists('meta', $data) && is_array($data['meta'])) {
				$record->setManyMeta($data['meta']);
			}

			DB::commit();
		} catch (\Throwable $th) {
			DB::rollBack();

			throw $th;
		}

		return $record;
	}
}
