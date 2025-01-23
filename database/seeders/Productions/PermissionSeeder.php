<?php

namespace Database\Seeders\Productions;

use App\Actions\Permissions\PermissionPopulateAction;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        try {
            PermissionPopulateAction::run();
        } catch (\Throwable $th) {
            // throw $th;
        }

    }
}
