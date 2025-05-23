@props(['key', 'item'])
<li>
    <button 
        x-navigation:trigger="{{ $key }}"
        {{ 
            $attributes->twMerge([
                'btn btn-ghost btn-sm rounded-full inline-flex items-center justify-center px-4 transition-colors hover:text-primary-content focus:outline-none disabled:opacity-50 disabled:pointer-events-none group w-max gap-0',
            ]) 
        }}
        >
        <span>{{ $item->getLabel() }}</span>
        <svg :class="{ '-rotate-180': isNavigationMenuOpen('{{ $key }}') }"
            class="relative top-[1px] ms-1 h-3 w-3 ease-out duration-300" xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" aria-hidden="true">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </button>
</li>
