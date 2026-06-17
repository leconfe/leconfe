<?php

namespace Tests\Unit;

use App\Utils\UpgradeSchema;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class UpgradeSchemaTest extends TestCase
{
    public function test_upgrade_from_1_4_4_to_1_5_0_beta_1_runs_upgrade_schema(): void
    {
        $schemas = UpgradeSchema::getSchemasByVersion('1.4.4', '1.5.0-beta.1');

        $this->assertArrayHasKey('1.5.0-beta.1', $schemas);
        $this->assertInstanceOf(
            'App\\Utils\\UpgradeSchemas\\Upgrade150Beta1',
            $schemas['1.5.0-beta.1'],
        );

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', ['--force' => true])
            ->andReturn(0);

        $schemas['1.5.0-beta.1']->run();
    }
}
