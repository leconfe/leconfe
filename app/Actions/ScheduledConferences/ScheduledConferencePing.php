<?php

namespace App\Actions\ScheduledConferences;

use App\Models\ScheduledConference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class ScheduledConferencePing
{
	use AsAction;

	public function handle(ScheduledConference $scheduledConference) : void
	{
		Cache::remember('scheduled_conference_ping_' . $scheduledConference->getKey(), now()->endOfDay(), fn () => $this->ping($scheduledConference));
	}

	public function ping(ScheduledConference $scheduledConference): bool
	{
		$response = Http::withToken($scheduledConference->getEntityToken())
			->acceptJson()
			->post(app()->getApiUrl('leconfe/entity/ping'), [
				'url' => $scheduledConference->getUrl(),
			]);

		return $response->successful();
	}
}
