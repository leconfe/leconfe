<?php

namespace App\Frontend\Website\Pages;

use App\Actions\Leconfe\UpgradeAction;
use App\Facades\MetaTag;
use App\Http\Middleware\RedirectToConference;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetupDefaultData;

class Upgrade extends Page
{
    protected static string $view = 'frontend.website.pages.upgrade';

    protected static string|array $withoutRouteMiddleware = [
        SetLocale::class,
        SetupDefaultData::class,
        RedirectToConference::class,
    ];

    public function mount()
    {
        MetaTag::add('robots', 'noindex, nofollow');

        if (version_compare(app()->getInstalledVersion(), app()->getCodeVersion(), '>=')) {
            return redirect('/');
        }
    }

    protected function getViewData(): array
    {
        return [
            'installedVersion' => app()->getInstalledVersion(),
            'codeVersion' => app()->getCodeVersion(),
        ];
    }

    public static function getLayout(): string
    {
        return 'frontend.website.components.layouts.base';
    }

    public function upgrade()
    {
        try {
            UpgradeAction::run();

            return redirect()->route('livewirePageGroup.website.pages.installation-successful');
        } catch (\Throwable $th) {
            $this->addError('upgrade', $th->getMessage());
        }

    }
}
