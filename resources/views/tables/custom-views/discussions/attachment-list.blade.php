<div>
    <div class="space-y-2 text-sm ">
        @forelse($getRecord()->getMedia('discussion-attachment') as $attachment)
            <a href="{{ $attachment->getTemporaryUrl(now()->addMinutes(5)) }}" target="_blank"
                class="flex justify-center text-primary-600 hover:text-primary-800">
                <x-lineawesome-file-alt-solid class="w-4 h-4 mr-2" />
                {{ $attachment->name }}
            </a>
        @empty
            <span class="text-sm text-gray-600">{{ __('no_attachments_found') }}</span>
        @endforelse
    </div>

</div>
