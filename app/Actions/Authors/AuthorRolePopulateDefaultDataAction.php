<?php

namespace App\Actions\Authors;

use App\Models\AuthorRole;
use App\Models\Conference;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthorRolePopulateDefaultDataAction
{
    use AsAction;

    public function handle(Conference $conference): void
    {
        try {
            DB::beginTransaction();

            foreach (['author' => 'authors', 'translator' => 'translators'] as $code => $citationGroup) {
                $role = AuthorRole::where('conference_id', $conference->getKey())
                    ->where('builtin_code', $code)->first();

                if (! $role) {
                    $role = AuthorRole::firstOrCreate([
                        'name' => AuthorRole::builtinNames()[$code],
                        'conference_id' => $conference->getKey(),
                    ]);

                    if ($role->wasRecentlyCreated) {
                        $role->forceFill(['builtin_code' => $code])->save();
                    }
                }

                $metaKey = 'citation_contributor_'.$citationGroup;
                $citedRoleIds = $conference->getMeta($metaKey);
                $citesAnotherConference = AuthorRole::withoutGlobalScopes()
                    ->whereKey($citedRoleIds ?? [])
                    ->where('conference_id', '!=', $conference->getKey())
                    ->exists();

                if ($citedRoleIds === null || $citesAnotherConference) {
                    $conference->setMeta($metaKey, [$role->getKey()]);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }
    }
}
