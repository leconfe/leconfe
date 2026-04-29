<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\ScheduledConference;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class Upgrade141 extends UpgradeBase
{
    private const READER_ROLE = 'Reader';

    public function run(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);

        $this->removeReaderRole();
        $this->newRoleParticipant();
    }

    private function removeReaderRole(): void
    {
        DB::transaction(function () {
            $readerRoleIds = Role::withoutGlobalScopes()
                ->where('name', self::READER_ROLE)
                ->pluck('id');

            if ($readerRoleIds->isNotEmpty()) {
                DB::table('model_has_roles')
                    ->whereIn('role_id', $readerRoleIds)
                    ->delete();

                Role::withoutGlobalScopes()
                    ->whereIn('id', $readerRoleIds)
                    ->delete();
            }

            DB::table('user_invitations')
                ->where('role_name', self::READER_ROLE)
                ->delete();

            Role::withoutGlobalScopes()
                ->where('name', self::READER_ROLE)
                ->delete();
        });

        ScheduledConference::withoutGlobalScopes()
            ->get()
            ->each(function (ScheduledConference $scheduledConference) {
                $allowedRoles = collect($scheduledConference->getMeta('allowed_self_assign_roles') ?? [])
                    ->reject(fn($role) => $role === self::READER_ROLE)
                    ->values()
                    ->all();

                $scheduledConference->setMeta('allowed_self_assign_roles', $allowedRoles);
            });
    }

    private function newRoleParticipant(): void
    {
        Permission::firstOrCreate([
            'name' => 'Payment:registerParticipant',
        ]);

        ScheduledConference::withoutGlobalScopes()
            ->chunk(100, function ($scheduledConferences): void {
                foreach ($scheduledConferences as $scheduledConference) {
                    Role::withoutGlobalScopes()->firstOrCreate([
                        'name' => UserRole::Participant->value,
                        'conference_id' => $scheduledConference->conference_id,
                        'scheduled_conference_id' => $scheduledConference->getKey(),
                    ]);
                }
            });
    }
}
