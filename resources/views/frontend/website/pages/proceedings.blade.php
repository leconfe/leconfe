@use('App\Models\Enums\SubmissionStatus')
@use('App\Constants\SubmissionFileCategory')
<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative mb-4">
        <x-website::heading-title :title="$this->getTitle()" class="mb-5" />
        <div class="grid grid-cols-1 md:grid-cols-2 sm:grid-cols-1 gap-x-4 gap-y-10">
            @forelse ($proceedings as $proceeding)
                <div class="pb-2 space-y-5 border-b">
                    <div class="flex space-x-5">
                        @if($proceeding->getFirstMediaUrl('cover'))
                            <a href="{{ route('livewirePageGroup.conference.pages.proceeding-detail', ['proceeding' => $proceeding]) }}" class="w-full h-36 max-h-36 max-w-[6rem]">
                                <img src="{{ $proceeding->getFirstMediaUrl('cover') }}" alt="">
                            </a>
                        @endif
                        <div>
                            <div class="mb-3 text-base space-y-0.5">
                                <h2>
                                    <a href="{{ route('livewirePageGroup.conference.pages.proceeding-detail', ['proceeding' => $proceeding]) }}" class="font-semibold text-gray-700 hover:text-primary">{{ $proceeding->title }}</a>
                                </h2>
                                <div class="text-sm text-gray-500">
                                    {{ $proceeding->volume ? 'Vol. '.$proceeding->volume : '' }}
                                    {{ $proceeding->number ? 'No. '.$proceeding->number : '' }}
                                    {{ $proceeding->year ? '('.$proceeding->year.')' : '' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-2 text-center text-gray-500">
                    No proceeding found.
                </div>
            @endforelse
        </div>
    </div>
</x-website::layouts.main>
