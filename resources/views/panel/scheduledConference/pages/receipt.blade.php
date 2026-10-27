<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app(\App\Support\Multilingual\ContentLocales::class)->direction() }}">

<head>
    <meta charset="UTF-8">
        <title>
            {{ __('general.receipt_document') }} - {{ $record->receipt }} - {{ $scheduledConference->title }}
        </title>

    @vite(['resources/panel/css/panel.css'])
    @filamentStyles

    <style>
        /* A4 print setup */
        @page {
            size: A4;
            /* margin: 20mm; */
        }

        @media print {
            body {
                margin: 0;
                /* font-family: Arial, sans-serif; */
                font-size: 12pt;
                color: #000;
            }

            .page {
                page-break-after: always;
            }
        }

        body {
            width: 210mm;
            min-height: 297mm;
            /* margin: auto; */
            background: #fff !important;
            padding-top: 5mm;
            box-sizing: border-box;
        }

        .page {
            width: 100%;
            height: auto;
            /* padding: 20mm; */
            background: white;
        }

        /* .invoice-table {
      table,th,td {
        border: 1px solid black;
      }
    } */
    </style>
</head>

<body class="text-sm">
    <div class="page">
        @if($scheduledConference->hasMedia('logo'))
        <img class="max-h-80"
            src="{{ $scheduledConference->getFirstMedia('logo')?->getAvailableUrl(['thumb', 'thumb-xl']) }}"
            alt="{{ $scheduledConference->title }}">
        @endif
        <div class="text-right">
            <p class="text-base font-bold">{{ $scheduledConference->title }}</p>
            {!! $scheduledConference->getMeta('receipt_sender_information') ?: $scheduledConference->getMeta('invoice_sender_information') !!}
        </div>
        <div class="bg-gray-200 p-2 mt-4">
            <p class="text-xl font-bold">{{ __('general.receipt_number_value', ['number' => $record->receipt]) }}</p>
             <p>{{ __('general.document_registration_date', ['date' => $record->created_at->locale(app()->getLocale())->isoFormat('LLL')]) }}</p>
        </div>
        <div class="mt-8 text-base">
            <p>{{ __('general.receipt_greeting', ['name' => $user_fullname]) }}</p>
            <p>
                {{ __('general.receipt_fee_received', ['amount' => $record->getFormattedFee()]) }}
            </p>
            @if($submissionTitle)
            <p>{{ __('general.document_submission_title_label') }} <b>{{ $submissionTitle }}</b></p>
            @endif
            @if(count($additionalItems))
            <p class="mt-2">{{ __('general.payment_breakdown') }}</p>
            <ul class="list-disc ml-6">
                <li>{{ __('general.base_fee_amount', ['amount' => money($baseAmount, $record->currency, true)->formatWithoutZeroes()]) }}</li>
                @foreach($additionalItems as $item)
                <li>
                    {{ data_get($item, 'name') }}
                    @if(data_get($item, 'quantity', 1) > 1)
                    x{{ data_get($item, 'quantity') }}
                    @endif
                    : {{ money((float) data_get($item, 'total_amount', data_get($item, 'amount', 0)), $record->currency, true)->formatWithoutZeroes() }}
                </li>
                @endforeach
            </ul>
            @endif
            <p>{{ __('general.receipt_thank_you') }}</p>
        </div>
        @php($receiptNotes = $scheduledConference->getMeta('receipt_notes'))
        @if($receiptNotes)
        <div class="mt-4 max-w-none prose prose-sm prose-p:my-0 prose-p:leading-5 prose-li:leading-5 prose-ol:mt-0" style="--tw-prose-body: #000;--tw-prose-counters: #000;">
            {!! $receiptNotes !!}
        </div>
        @endif
    </div>
</body>

</html>
