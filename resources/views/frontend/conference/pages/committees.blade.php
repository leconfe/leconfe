<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="flex mb-5 space-x-4">
        <h1 class="text-xl font-semibold min-w-fit"> {{ __('translation.pagesBlade.committeesListCommittee') }}</h1>
        <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
    </div>
    <div class="space-y-4">
        @forelse ($committeeRoles as $role)
            <div class="">
                <h2 class="text-xl">{{ $role->name }}</h2>
                <div class="flex flex-wrap w-full gap-3">
                    @foreach ($role->committees as $committee)
                        <div class="card card-compact border">
                            <div class="card-body">
                                <div class="flex flex-col gap-4">
                                    <div class="flex gap-x-2">
                                            <div class="profile-image avatar">
                                                <div class="w-12 h-12 rounded-full">
                                                    <img src="{{ $committee->getFilamentAvatarUrl() }}"
                                                        alt="{{ $committee->fullName }}" />
                                                </div>
                                            </div>
                                        <div class="profile-description">
                                            <p class="text-content">{{ $committee->fullName }}</p>
                                            @if ($committee->hasMeta('affiliation'))
                                                <span class="text-xs">{{ $committee->getMeta('affiliation') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($committee->hasMeta('expertise'))
                                        <div class="inline-flex flex-wrap gap-2">
                                            @foreach ($committee->getMeta('expertise') as $expertise)
                                                <div class="h-6 text-xs border border-gray-300 badge badge-outline">
                                                    {{ $expertise }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="col-span-2 text-center text-gray-500">
                {{ __('translation.pagesBlade.committeesNocommiitteesFound') }}
            </div>
        @endforelse
    </div>
</x-website::layouts.main>