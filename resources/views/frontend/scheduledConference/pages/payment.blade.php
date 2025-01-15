<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative space-y-6">
        <div class="flex mb-5 space-x-4">
            <h1 class="text-xl font-semibold min-w-fit">{{ $this->getTitle() }}</h1>
            <hr class="w-full h-px my-auto bg-gray-200 border-0 dark:bg-gray-700">
        </div>
        <div class="payment-information">
            <div class="overflow-x-auto">
                <table class="table border border-base-200">
                    <tbody>
                        <tr>
                            <th class="sm:w-48">Title</td>
                            <td>
                                {{ $paymentQueue->getMeta('title') }}
                            </td>
                        </tr>
                        <tr>
                            <th class="sm:w-48">Payment Type</td>
                            <td>{{ $paymentQueue->getPaymentType() }}</td>
                        </tr>
                        <tr>
                            <th class="sm:w-48">Fee</th>
                            <td>{{ $paymentQueue->getFormattedFee()}}</td>
                        </tr>
                        <tr>
                            <th class="sm:w-48">Description</th>
                            <td>{{ $paymentQueue->getMeta('description')}}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="payment-methods join join-vertical w-full">
            @foreach ($paymentDetails as $paymentTitle => $paymentDescription)
                <div class="payment-method collapse collapse-arrow join-item border-base-200 border">
                    <input type="radio" name="payment-detail"
                        @if ($loop->first) checked="checked" @endif />
                    <div class="collapse-title text-lg font-medium">{{ $paymentTitle }}</div>
                    <div class="collapse-content user-content">
                        {!! new Illuminate\Support\HtmlString($paymentDescription) !!}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-website::layouts.main>
