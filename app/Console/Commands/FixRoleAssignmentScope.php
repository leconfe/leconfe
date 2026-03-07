<?php

namespace App\Console\Commands;

use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixRoleAssignmentScope extends Command
{
    protected $signature = 'roles:fix-assignment-scope
                            {--role-id=* : Limit the fix to specific role IDs}
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Fix model_has_roles rows whose conference or scheduled conference scope does not match the assigned role.';

    public function handle(): int
    {
        $requestedRoleIds = collect($this->option('role-id'))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->values();
        $dryRun = (bool) $this->option('dry-run');
        $roleIds = Role::withoutGlobalScopes()
            ->where('scheduled_conference_id', 0)
            ->when($requestedRoleIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $requestedRoleIds))
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            $this->info('No conference-scoped roles found to process.');

            return self::SUCCESS;
        }

        $mismatchedAssignments = DB::table('model_has_roles as mhr')
            ->select([
                'mhr.role_id',
                'mhr.conference_id as pivot_conference_id',
                'mhr.scheduled_conference_id as pivot_scheduled_conference_id',
                'mhr.model_type',
                'mhr.model_id',
            ])
            ->whereIn('mhr.role_id', $roleIds)
            ->where('mhr.scheduled_conference_id', '!=', 0)
            ->orderBy('mhr.role_id')
            ->orderBy('mhr.model_type')
            ->orderBy('mhr.model_id')
            ->get();

        if ($mismatchedAssignments->isEmpty()) {
            $this->info('No mismatched role assignments found.');

            return self::SUCCESS;
        }

        $existingAssignments = DB::table('model_has_roles')
            ->select([
                'role_id',
                'conference_id',
                'scheduled_conference_id',
                'model_type',
                'model_id',
            ])
            ->whereIn('role_id', $roleIds)
            ->get()
            ->keyBy(fn ($row) => $this->makeKey(
                (int) $row->role_id,
                (string) $row->model_type,
                (int) $row->model_id,
                (int) $row->conference_id,
                (int) $row->scheduled_conference_id,
            ));

        $updates = [];
        $deletes = [];

        foreach ($mismatchedAssignments as $assignment) {
            $sourceKey = $this->makeKey(
                (int) $assignment->role_id,
                (string) $assignment->model_type,
                (int) $assignment->model_id,
                (int) $assignment->pivot_conference_id,
                (int) $assignment->pivot_scheduled_conference_id,
            );

            $targetKey = $this->makeKey(
                (int) $assignment->role_id,
                (string) $assignment->model_type,
                (int) $assignment->model_id,
                (int) $assignment->pivot_conference_id,
                0,
            );

            if ($existingAssignments->has($targetKey)) {
                $deletes[] = [
                    'role_id' => (int) $assignment->role_id,
                    'conference_id' => (int) $assignment->pivot_conference_id,
                    'scheduled_conference_id' => (int) $assignment->pivot_scheduled_conference_id,
                    'model_type' => (string) $assignment->model_type,
                    'model_id' => (int) $assignment->model_id,
                ];

                $existingAssignments->forget($sourceKey);

                continue;
            }

            $updates[] = [
                'match' => [
                    'role_id' => (int) $assignment->role_id,
                    'conference_id' => (int) $assignment->pivot_conference_id,
                    'scheduled_conference_id' => (int) $assignment->pivot_scheduled_conference_id,
                    'model_type' => (string) $assignment->model_type,
                    'model_id' => (int) $assignment->model_id,
                ],
                'values' => [
                    'scheduled_conference_id' => 0,
                ],
            ];

            $existingAssignments->forget($sourceKey);
            $existingAssignments->put($targetKey, (object) [
                'role_id' => (int) $assignment->role_id,
                'conference_id' => (int) $assignment->pivot_conference_id,
                'scheduled_conference_id' => 0,
                'model_type' => (string) $assignment->model_type,
                'model_id' => (int) $assignment->model_id,
            ]);
        }

        $this->info(sprintf(
            'Found %d mismatched assignments: %d update(s), %d duplicate delete(s).',
            $mismatchedAssignments->count(),
            count($updates),
            count($deletes),
        ));

        if ($dryRun) {
            $this->comment('Dry run only. No database changes were made.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates, $deletes) {
            foreach ($deletes as $delete) {
                DB::table('model_has_roles')
                    ->where($delete)
                    ->delete();
            }

            foreach ($updates as $update) {
                DB::table('model_has_roles')
                    ->where($update['match'])
                    ->update($update['values']);
            }
        });

        $this->info('Role assignment scopes fixed successfully.');

        return self::SUCCESS;
    }

    protected function makeKey(
        int $roleId,
        string $modelType,
        int $modelId,
        int $conferenceId,
        int $scheduledConferenceId,
    ): string {
        return implode('|', [
            $roleId,
            $modelType,
            $modelId,
            $conferenceId,
            $scheduledConferenceId,
        ]);
    }
}
