<?php

namespace App\Utils\UpgradeSchemas;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class Upgrade130 extends UpgradeBase
{
    public function run(): void
    {
		$this->deleteTable();
    }

	public function deleteTable()
	{
		Schema::dropIfExists('registration_attendances');
		Schema::dropIfExists('registration_payments');
		Schema::dropIfExists('registration_types');
		Schema::dropIfExists('registrations');
	}
}
