@use('App\Models\Enums\SubmissionStatus')
@use('App\Constants\SubmissionFileCategory')
<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative mb-4">
        <div class="flex mb-5 space-x-4">
            <div class="text-xl font-semibold min-w-fit"> {{__('translation.pagesBlade.proceedingsListProceeding')}}</div>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
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
                                <a href="{{ route('livewirePageGroup.conference.pages.proceeding-detail', ['proceeding' => $proceeding]) }}" class="font-semibold text-gray-700 hover:text-primary">{{ $proceeding->title }}</a>
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
                    {{__('translation.pagesBlade.proceedingsNoProceedingFound')}}
                </div>
            @endforelse
        </div>
    </div>
</x-website::layouts.main>
