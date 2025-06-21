<?php

namespace App\Utils\UpgradeSchemas;

use App\Actions\Registrations\PopulateRegistrationFormAction;
use App\Models\ScheduledConference;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class Upgrade130Beta2 extends UpgradeBase
{
    public function run(): void
    {
		$this->deleteTable();
		$this->migrate();
		$this->populateReviewForms();
    }

	protected function deleteTable()
	{
		Schema::dropIfExists('registration_attendances');
		Schema::dropIfExists('registration_payments');
		Schema::dropIfExists('registration_types');
		Schema::dropIfExists('registrations');
	}

	protected function migrate(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }

	protected function populateReviewForms(): void
	{
		ScheduledConference::query()
			->withoutGlobalScopes()
			->each(function (ScheduledConference $scheduledConference) {
				PopulateRegistrationFormAction::run($scheduledConference);
			});
	}
}
