<x-filament-widgets::widget>
    @hook('Panel::ScheduledConference::DashboardOverviewBefore')

    {{ $this->scheduledConferenceInfolist }}

    @hook('Panel::ScheduledConference::DashboardOverviewAfter')

    <x-filament-actions::modals />
</x-filament-widgets::widget>
