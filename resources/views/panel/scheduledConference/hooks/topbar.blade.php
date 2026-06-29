<div class="hidden sm:flex flex-col">
    <a href="{{ app()->getCurrentScheduledConference()->getHomeUrl() }}" class="text-lg font-medium">{{ app()->getCurrentScheduledConference()->title }}</a>
    @hook('Panel::ScheduledConference::TopbarAfterTitle')
</div>
