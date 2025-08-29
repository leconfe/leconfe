@php
    $childComponentContainers = $getChildComponentContainers();
    $hasMultipleContainers = count($childComponentContainers) > 1;
@endphp

<div
    class="fi-multilanguage-field w-full transition-all grid gap-2"
    @if($hasMultipleContainers)
        x-cloak
        x-data="{focus: false}"
        x-on:click.outside="focus = false;"
        x-on:click="focus = true"
    @endif
>
    @foreach ($getChildComponentContainers() as $componentContainer)
        <div
            @if(!$loop->first) 
            x-show="focus" 
            x-transition.opacity
            @endif
            >
            {{ $componentContainer }}
        </div>
    @endforeach
</div>
