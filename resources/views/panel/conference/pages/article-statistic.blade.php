<x-filament::page>
    <div class="space-y-4">
        <div class="flex flex-wrap flex-row-reverse items-center justify-between gap-6" >
            <x-filament::dropdown placement="bottom-end" width="sm">
                <x-slot name="trigger">
                    <x-filament::button
                        icon="heroicon-s-funnel">
                        Filter
                    </x-filament::button>
                </x-slot>

                <x-filament::dropdown.list>
                    <x-filament::dropdown.list>
                        {{ $this->form }}
                    </x-filament::dropdown.list>
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>

        @livewire(App\Panel\Conference\Widgets\ArticleStatisticChart::class, ['statistic' => $this->data], key('article-statistic-chart'))

        {{ $this->table }}
    </div>
</x-filament::page>
