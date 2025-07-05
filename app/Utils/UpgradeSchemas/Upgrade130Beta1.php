<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Submission;
use Illuminate\Support\Facades\Artisan;

class Upgrade130Beta1 extends UpgradeBase
{
    public function run(): void
    {
        $this->updateAuthorPrimaryContact();
        $this->migrate();
    }

    protected function updateAuthorPrimaryContact(): void 
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

    protected function migrate(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }
}
