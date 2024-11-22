@props(['partner'])


<div {{ $attributes->class(['conference-partner']) }}>
    @if ($partner->getMeta('url'))
        <a href="{{ $partner->getMeta('url') }}" target="_blank" rel="noopener noreferrer">
    @endif
        <img class="conference-partner-logo max-h-32" src="{{ $partner->getFirstMediaUrl('logo') }}" alt="{{ $partner->name }}" />
    @if ($partner->getMeta('url'))
        </a>
    @endif
</div>
