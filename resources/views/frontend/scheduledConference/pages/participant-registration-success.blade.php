<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative space-y-4">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ $this->getTitle() }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        <div class="overflow-x-auto">
            <table class="table table-xs sm:table-md">
                <tbody>
                    <tr>
                        <th class="w-40">Full Name</th>
                        <td class="w-4">:</td>
                        <td>{{$participant->full_name}}</td>
                    </tr>
                    <tr>
                        <th class="w-40">Email</th>
                        <td class="w-4">:</td>
                        <td>{{$participant->email}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-xs sm:table-md">
                <tbody>
                    <tr>
                        <th class="w-40">Participant</th>
                        <td class="w-4">:</td>
                        <td>{{$participant->payment->getMeta('title')}}</td>
                    </tr>
                    <tr>
                        <th class="w-40">Description</th>
                        <td class="w-4">:</td>
                        <td>{{$participant->payment->getMeta('description')}}</td>
                    </tr>
                    <tr>
                        <th class="w-40">Amount</th>
                        <td class="w-4">:</td>
                        <td>{{$participant->payment->getFormattedFee()}}</td>
                    </tr>
                    <tr>
                        <th class="w-40">Payment Method</th>
                        <td class="w-4">:</td>
                        <td>{{Str::title($participant->payment->payment_method)}}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="sm:p-4">
            <p class="">Thank you for your registration, we will provide further information via email.</p>
        </div>
    </div>
</x-website::layouts.main>
