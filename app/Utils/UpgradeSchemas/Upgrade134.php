<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\ScheduledConference;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\UserRole;
use App\Models\Role;

class Upgrade134 extends UpgradeBase
{
    public function run(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }

    public function migrateRole()
    {
        $scheduledConferences = ScheduledConference::withoutGlobalScopes()->get();
		$migrateRoles = [UserRole::Author, UserRole::Reviewer];

		foreach ($scheduledConferences as $scheduledConference) {
			foreach ($migrateRoles as $role) {
				$conferenceRole = Role::withoutGlobalScopes()
					->where('name', $role->value)
					->where('conference_id', $scheduledConference->conference_id)
					->where('scheduled_conference_id', 0)
					->first();
	
	
				if ($conferenceRole) {
					$scheduledConferenceRole = Role::withoutGlobalScopes()->firstOrCreate([
						'name' => $role->value,
						'conference_id' => $scheduledConference->conference_id,
						'scheduled_conference_id' => $scheduledConference->getKey(),
					]);
	
					$userIds = DB::table('model_has_roles')
						->where('role_id', $conferenceRole->getKey())
						->where('conference_id', $scheduledConference->conference_id)
						->pluck('model_id')
						->map(fn($userId) => [
							'role_id' => $scheduledConferenceRole->getKey(),
							'conference_id' => $scheduledConference->conference_id,
							'scheduled_conference_id' => $scheduledConference->getKey(),
							'model_type' => User::class,
							'model_id' => $userId,
						]);
	
					DB::table('model_has_roles')
						->insertOrIgnore($userIds->toArray());

					DB::table('submission_has_participants')
						->where('role_id', $conferenceRole->getKey())
						->update(['role_id' => $scheduledConferenceRole->getKey()]);
				}
			}
		}

		Role::withoutGlobalScopes()
			->whereIn('name', [UserRole::Author, UserRole::Reviewer])
			->where('scheduled_conference_id', 0)
			->lazy()
			->each
			->delete();
    }
}
