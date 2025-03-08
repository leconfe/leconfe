<x-filament-panels::page>
    {{-- <x-filament::section>
        <x-slot name="heading">
            Version history
        </x-slot>



        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Version
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Major
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Minor
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Patch
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Date Installed
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-white border-b border-gray-200">
                        <th scope="row"
                            class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                            1.0.0
                        </th>
                        <td class="px-6 py-4">
                            1
                        </td>
                        <td class="px-6 py-4">
                            0
                        </td>
                        <td class="px-6 py-4">
                            0
                        </td>
                        <td class="px-6 py-4">
                            2023-08-21
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </x-filament::section> --}}

    <div class="space-y-4">
        <h2 class="text-xl font-medium">Current Version</h2>
        <p class="font-medium">
            {{ $currentVersion->version }} ({{ $currentVersion->created_at }}) - <a class="text-primary-600 hover:text-primary-800" href="https://github.com/OpenSynergic/leconfe/releases" target="_blank">See Changelog</a>
        </p>
    </div>
    <div class="space-y-4">
        <h2 class="text-xl font-medium">Version History</h2>
        <div class="relative overflow-x-auto border" >
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 ">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            Version
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Major
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Minor
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Patch
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Date Installed
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($versions as $version)
                        <tr class="bg-white border-b border-gray-200">
                            <th scope="row"
                                class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                {{ $version->version }}
                            </th>
                            <td class="px-6 py-4">
                                {{ $version->major }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $version->minor }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $version->patch }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $version->created_at->format('Y-m-d') }}
                            </td>
                        </tr>        
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</x-filament-panels::page>
