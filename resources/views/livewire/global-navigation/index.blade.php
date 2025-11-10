<nav 
    class="relative w-min" 
    x-data="{ 
        openModal(){
            if(!this.$wire.opened){
                this.$wire.open();
            }
            globalsearch.showModal();
        }
    }"
    >
    <button 
        x-on:click="openModal()"
        x-on:keydown.meta.k.window="openModal()"
        class="navigation-menu-item btn btn-ghost no-animation btn-sm rounded-lg inline-flex items-center justify-center px-4 transition-colors focus:outline-none disabled:opacity-50 disabled:pointer-events-none group w-max gap-0 ease-out duration-300">
        <span>Conferences</span>
        <x-heroicon-m-chevron-down class="transition relative top-[1px] ms-1 h-3 w-3" />
    </button>
    <dialog id="globalsearch" class="modal" wire:ignore.self x-cloak>
        <div class="flex min-h-[370px] justify-center w-full max-w-xl items-start modal-box bg-transparent shadow-none p-0" x-cloak>
            <div class="flex flex-col w-full h-full overflow-hidden bg-white border rounded-lg shadow-md">
                <div class="flex items-center px-3 border-b">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 me-0 text-gray-400 shrink-0" />
                    <input 
                        wire:model.live.debounce='search'
                        type="text" 
                        placeholder="Type to search..." 
                        autocomplete="off" 
                        autocorrect="off" 
                        spellcheck="false"
                        class="flex w-full px-2 py-3 text-sm bg-transparent border-0 rounded-md outline-none focus:outline-none focus:ring-0 focus:border-0 placeholder:text-gray-400 h-11 disabled:cursor-not-allowed disabled:opacity-50" 
                        >
                </div>
                <div class="max-h-[320px] overflow-y-auto overflow-x-hidden">
                    <div class="p-2" wire:loading.delay> 
                        <x-filament::loading-indicator class="h-5 w-5 animate-spin" />
                    </div>
                    <div class="p-2 overflow-hidden text-gray-700" wire:loading.remove.delay>
                        @foreach ($searchResults as $result)
                            {!! $result !!}
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</nav>