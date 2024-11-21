@props(['sponsor'])


<div {{ $attributes->class(['conference-sponsor']) }}>
    @if ($sponsor->getMeta('url'))
        <a href="{{ $sponsor->getMeta('url') }}" target="_blank" rel="noopener noreferrer">
    @endif
        <img class="conference-sponsor-logo max-h-32" src="{{ $sponsor->getFirstMediaUrl('logo') }}"
                alt="{{ $sponsor->name }}" />
    @if ($sponsor->getMeta('url'))
        </a>
    @endif
</div>
