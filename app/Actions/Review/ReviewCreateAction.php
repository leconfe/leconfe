<?php

namespace App\Actions\Review;

use App\Models\Review;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReviewCreateAction
{
	use AsAction;

	public function handle(array $data): Review
	{
		try {
			DB::beginTransaction();

			$record = Review::create($data);

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
