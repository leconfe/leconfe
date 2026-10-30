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

    public function test_upgrade_from_1_5_0_beta_1_to_1_5_0_beta_2_runs_upgrade_schema(): void
    {
        $schemas = UpgradeSchema::getSchemasByVersion('1.5.0-beta.1', '1.5.0-beta.2');

        $this->assertArrayHasKey('1.5.0-beta.2', $schemas);
        $this->assertInstanceOf(
            'App\\Utils\\UpgradeSchemas\\Upgrade150Beta2',
            $schemas['1.5.0-beta.2'],
        );

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', ['--force' => true])
            ->andReturn(0);

        $schemas['1.5.0-beta.2']->run();
    }

    public function test_upgrade_from_1_5_0_beta_2_to_1_5_0_beta_3_runs_upgrade_schema(): void
    {
        $schemas = UpgradeSchema::getSchemasByVersion('1.5.0-beta.2', '1.5.0-beta.3');

        $this->assertArrayHasKey('1.5.0-beta.3', $schemas);
        $this->assertInstanceOf(
            'App\\Utils\\UpgradeSchemas\\Upgrade150Beta3',
            $schemas['1.5.0-beta.3'],
        );

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', ['--force' => true])
            ->andReturn(0);

        $schemas['1.5.0-beta.3']->run();
    }

    public function test_upgrade_from_1_5_1_to_1_5_3_runs_upgrade_schema(): void
    {
        $schemas = UpgradeSchema::getSchemasByVersion('1.5.1', '1.5.3');

        $this->assertArrayHasKey('1.5.3', $schemas);
        $this->assertInstanceOf(
            'App\\Utils\\UpgradeSchemas\\Upgrade153',
            $schemas['1.5.3'],
        );

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', ['--force' => true])
            ->andReturn(0);

        $schemas['1.5.3']->run();
    }

    public function test_upgrade_from_1_5_2_to_1_5_3_runs_upgrade_schema(): void
    {
        $schemas = UpgradeSchema::getSchemasByVersion('1.5.2', '1.5.3');

        $this->assertArrayHasKey('1.5.3', $schemas);
    }

    public function test_upgrade_from_1_5_3_to_1_6_0_runs_upgrade_schema(): void
    {
        $schemas = UpgradeSchema::getSchemasByVersion('1.5.3', '1.6.0');

        $this->assertSame(['1.6.0'], array_keys($schemas));
        $this->assertInstanceOf(
            'App\\Utils\\UpgradeSchemas\\Upgrade160',
            $schemas['1.6.0'],
        );

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', ['--force' => true])
            ->andReturn(0);

        $schemas['1.6.0']->run();
    }

    public function test_upgrade_from_1_5_1_to_1_6_0_runs_both_upgrade_schemas(): void
    {
        $schemas = UpgradeSchema::getSchemasByVersion('1.5.1', '1.6.0');

        $this->assertSame(['1.5.3', '1.6.0'], array_keys($schemas));
    }
}
