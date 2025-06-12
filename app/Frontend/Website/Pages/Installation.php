<?php

namespace App\Frontend\Website\Pages;

use App\Facades\Hook;
use App\Facades\MetaTag;
use App\Http\Middleware\RedirectToConference;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetupDefaultData;
use App\Http\Middleware\ThemeActivator;
use App\Livewire\Forms\InstallationForm;
use App\Utils\Installer;
use App\Utils\PermissionChecker;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Jackiedo\Timezonelist\Facades\Timezonelist;

class Installation extends Page
{
    protected static string $view = 'frontend.website.pages.installation';

    protected static string|array $withoutRouteMiddleware = [
        SetLocale::class,
        SetupDefaultData::class,
        RedirectToConference::class,
        ThemeActivator::class,
    ];

    public array $folders = [];

    public InstallationForm $form;

    public bool $installationSuccessful = false;

    public function mount()
    {
        if (App::isInstalled()) {
            return redirect('/');
        }

        MetaTag::add('robots', 'noindex, nofollow');

        Hook::add('Frontend::Views::Head', function ($hookName, &$output) {
            $output .= Blade::render("@vite(['resources/frontend/css/frontend.css'])");
        });

        $this->checkPermission();
    }

    protected function getViewData(): array
    {
        return [
            'groupedTimezone' => Timezonelist::toArray(false),
        ];
    }

    public static function getLayout(): string
    {
        return 'frontend.website.components.layouts.base';
    }

    public function checkPermission()
    {
        $permissionChecker = app(PermissionChecker::class);

        $this->folders = $permissionChecker->checkFolders([
            'storage/framework/' => 'storage/framework/',
            'storage/logs/' => 'storage/logs/',
            'storage/app/public/' => 'storage/app/public/',
            'bootstrap/cache/' => 'bootstrap/cache/',
        ]);
    }

    public function testConnection()
    {
        if ($this->form->checkDatabaseConnection()) {
            session()->flash('testConnection', true);
        }
    }

    public function install()
    {
        if (! $this->validateInstallation()) {
            return;
        }

        try {
            $installer = new Installer($this->form->getHydratedData());
            $installer->run();

            return redirect()->route('livewirePageGroup.website.pages.installation-successful');
        } catch (\Throwable $th) {
            $this->form->addError('error', $th->getMessage());
            Log::error($th);
        }

    }

    public function validateInstallation(): bool
    {
        $this->form->validate();

        if (! $this->form->checkDatabaseConnection()) {
            return false;
        }

        if (! $this->form->createDatabase()) {
            return false;
        }

        return true;
    }
}
