<?php

namespace Tests\Feature;

use App\Actions\Leconfe\UpgradeAction;
use App\Frontend\Website\Pages\Upgrade;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class UpgradePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_page_is_accessible_without_authentication_while_upgrading(): void
    {
        $this->prepareUpgradingApplication();

        $this->withoutVite()
            ->get(route(Upgrade::getRouteName('website')))
            ->assertOk()
            ->assertSee('Installed Version')
            ->assertSee('v0.0.1');
    }

    public function test_upgrade_action_can_run_without_authentication_while_upgrading(): void
    {
        $this->prepareUpgradingApplication();

        UpgradeAction::allowToRun();

        Livewire::test(Upgrade::class)
            ->call('upgrade')
            ->assertRedirect(route('livewirePageGroup.website.pages.installation-successful'));
    }

    protected function prepareUpgradingApplication(): void
    {
        Config::set('app.installed', true);

        Version::query()->create([
            'product_name' => 'Leconfe',
            'product_folder' => 'leconfe',
            'version' => '0.0.1',
        ]);
    }
}
