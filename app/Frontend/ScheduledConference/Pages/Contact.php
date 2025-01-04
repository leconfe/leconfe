<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Contact extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.contact';

    public function mount() {}

    public function getTitle(): string|Htmlable
    {
        return __('general.contact');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.contact'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $currentScheduledConference = app()->getCurrentScheduledConference();

        return [
            'mailing_address' => $currentScheduledConference?->getMeta('mailing_address'),
            'principal_contact_name' => $currentScheduledConference?->getMeta('principal_contact_name'),
            'principal_contact_email' => $currentScheduledConference?->getMeta('principal_contact_email'),
            'principal_contact_phone' => $currentScheduledConference?->getMeta('principal_contact_phone'),
            'principal_contact_affiliation' => $currentScheduledConference?->getMeta('principal_contact_affiliation'),
            'support_contact_name' => $currentScheduledConference?->getMeta('support_contact_name'),
            'support_contact_email' => $currentScheduledConference?->getMeta('support_contact_email'),
            'support_contact_phone' => $currentScheduledConference?->getMeta('support_contact_phone'),
        ];
    }
}
