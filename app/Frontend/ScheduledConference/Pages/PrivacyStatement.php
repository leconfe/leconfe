<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\ScheduledConference\Pages\Concerns\HasScheduledConferenceAuthLogo;
use App\Frontend\Website\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

class PrivacyStatement extends Page
{
    use HasScheduledConferenceAuthLogo;

    protected static string $view = 'frontend.scheduledConference.pages.privacy-statement';

    protected static string $layout = 'frontend.scheduledConference.components.layout.simple-with-platform-footer';

    public function mount()
    {
        //
    }

    public static function getLayout(): string
    {
        return static::$layout;
    }

    public function getMaxWidth(): MaxWidth|string|null
    {
        return MaxWidth::FourExtraLarge;
    }

    protected function getLayoutData(): array
    {
        return [
            'maxWidth' => $this->getMaxWidth(),
        ];
    }

    /**
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [static::class];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.privacy_statement'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'privacyStatement' => app()->getCurrentScheduledConference()->getMeta('privacy_statement'),
        ];
    }
}
