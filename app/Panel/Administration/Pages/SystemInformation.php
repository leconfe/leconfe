<?php

namespace App\Panel\Administration\Pages;

use App\Infolists\Components\LivewireEntry;
use App\Infolists\Components\ShoutUpdateVersion;
use App\Infolists\Components\VerticalTabs;
use App\Models\Version;
use App\Panel\Administration\Livewire\LanguageSetting;
use App\Panel\Administration\Livewire\SetupSetting;
use App\Panel\Administration\Livewire\SidebarSetting;
use App\Panel\Administration\Livewire\ThemeSetting;
use App\Panel\Conference\Livewire\NavigationMenuSetting;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class SystemInformation extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-m-cog';

    protected static string $view = 'panel.administration.pages.system-information';

    protected static bool $shouldRegisterNavigation = false;

    public function mount() {}

    public static function canAccess(): bool
    {
        return Auth::user()->can('update', app()->getSite());
    }

    public function getTitle(): string | Htmlable
    {
        return __('general.system_information');
    }

	public function getViewData() : array
	{
		$versions = Version::query()
            ->where('product_name', 'Leconfe')
            ->where('product_folder', 'leconfe')
            ->orderBy('installed_at', 'desc')
            ->get();

		return [
			'versions' => $versions,
			'currentVersion' => Version::application(),
		];
	}
}
