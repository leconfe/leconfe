<?php

namespace App\Actions\Registrations;

use App\Models\RegistrationForm;
use App\Models\Review;
use App\Models\ScheduledConference;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PopulateRegistrationFormAction
{
	use AsAction;

	public function handle(ScheduledConference $scheduledConfernce): void
	{
		$givenNameItem = RegistrationForm::firstOrCreate([
			'label' => 'Given Name',
			'type' => RegistrationForm::TYPE_TEXT,
			'scheduled_conference_id' => $scheduledConfernce->getKey(),
		], [
			'is_default' => true,
		]);
		$givenNameItem->setManyMeta([
			'description' => 'Enter your given name',
			'required' => true,
		]);

		$familyNameItem = RegistrationForm::firstOrCreate([
			'label' => 'Family Name',
			'type' => RegistrationForm::TYPE_TEXT,
			'scheduled_conference_id' => $scheduledConfernce->getKey(),
		], [
			'is_default' => true,
		]);
		$familyNameItem->setManyMeta([
			'description' => 'Enter your family name',
			'required' => true,
		]);

		$emailItem = RegistrationForm::firstOrCreate([
			'label' => 'Email',
			'type' => RegistrationForm::TYPE_TEXT,
			'scheduled_conference_id' => $scheduledConfernce->getKey(),
		], [
			'is_default' => true,
		]);
		$emailItem->setManyMeta([
			'description' => 'Enter your email address',
			'required' => true,
		]);

		$registrationTypeItem = RegistrationForm::firstOrCreate([
			'label' => 'Registration Type',
			'type' => RegistrationForm::TYPE_REGISTRATION_TYPE,
			'scheduled_conference_id' => $scheduledConfernce->getKey(),
		], [
			'is_default' => true,
		]);
		$registrationTypeItem->setManyMeta([
			'required' => true,
		]);
	}
}
