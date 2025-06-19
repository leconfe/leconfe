<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Submission;

class Upgrade130Beta1 extends UpgradeBase
{
    public function run(): void
    {
        Submission::query()
            ->with([
                'authors' => fn($query) => $query->ordered(),
                'meta'
            ])
            ->withoutGlobalScopes()
            ->lazy()
            ->each(function (Submission $submission) {
                $author = $submission->authors->first();

                if ($author) {
                    $submission->setPrimaryContact($author);
                }
            });
    }
}
