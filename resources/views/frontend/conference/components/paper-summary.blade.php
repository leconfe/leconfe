@props([
    'paper',
    'hideAuthor' => false,
])

<div class="paper-summary flex flex-col sm:flex-row gap-2 sm:gap-4">
    @if($paper->getFirstMediaUrl('cover'))
        <a href="{{ $paper->getPublicUrl() }}" class="cover max-w-56 grow">
            <img src="{{ $paper->getFirstMediaUrl('cover') }}" class="w-full" alt="Paper Cover">
        </a>
    @endif
    <div class="flex-1">
        <h3 class="title text-base">
            <a 
                target="{{ $paper->isPublishedOnExternal() ? '_blank' : '_self' }}"
                href="{{ $paper->getPublicUrl() }}" 
                class="font-semibold text-gray-700 hover:text-primary flex items-center"
                >
                {{ $paper->getMeta('title') }}
                @if($paper->isPublishedOnExternal())
                    <x-lineawesome-external-link-alt-solid class="w-4 h-4 ml-1" />
                @endif
            </a>
        </h3>
        @if($paper->getMeta('subtitle'))
            <div class="text-xs text-gray-500">{{ $paper->getMeta('subtitle') }}</div>
        @endif
        <div class="meta space-y-2">
            <div class="flex-1 flex gap-y-2 flex-wrap items-center justify-between">
                @if(!$hideAuthor && $paper->authors->isNotEmpty())
                    <div class="authors text-sm text-gray-600">
                        {{ $paper->authors->implode('fullName', ', ') }}
                    </div>
                @endif
                @if($paper->getMeta('article_pages'))
                    <div class="flex justify-start sm:justify-end gap-x-1 text-sm text-gray-600">
                        <x-heroicon-o-document-text class="w-4 h-4 my-auto text-gray-600" />
                        <span>{{ $paper->getMeta('article_pages') }}</span>
                    </div>
                @endif
            </div>
            @if($paper->doi)
                <div class="doi">
                    <a href="{{ $paper->doi->getUrl() }}" class="flex space-x-1 text-primary text-sm w-max">
                        <x-academicon-doi class="w-4 h-4 my-auto text-yellow-400" />
                        <span>{{ $paper->doi->doi }}</span>
                    </a>
                </div>
            @endif
            @if($paper->galleys->isNotEmpty())
                <div class="flex space-x-1.5">
                    @foreach ($paper->galleys as $galley)
                        <x-scheduledConference::galley-link :galley="$galley"/>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>