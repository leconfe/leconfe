<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-warning-600" />

                <div class="space-y-1">
                    <p class="text-sm font-semibold text-gray-900">
                        You are not registered in this scheduled conference.
                    </p>

                    @if ($scheduledConference)
                        <p class="text-sm text-gray-600">
                            Assign a role to continue working in {{ $scheduledConference->title }}.
                        </p>
                    @endif
                </div>
            </div>

            <div class="shrink-0">
                @if ($availableRoles->isNotEmpty())
                    {{ $this->assignRoleAction }}
                @else
                    <x-filament::badge color="gray">
                        No roles available
                    </x-filament::badge>
                @endif
            </div>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>