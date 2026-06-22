<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
        <title>
            Receipt - {{ $record->receipt }} - {{ $scheduledConference->title }}
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
            <p class="text-xl font-bold">Receipt No: {{ $record->receipt }}</p>
             <p>Registration Date: {{ $record->created_at->format('jS M Y, h:i:sa') }} </p>
        </div>
        <div class="mt-8 text-base">
            <p>Dear {{ $user_fullname }},</p>
            <p>
                We have successfully received your registration fee of {{ $record->getFormattedFee() }}.
            </p>
            @if($submissionTitle)
            <p>Submission Title: <b>{{ $submissionTitle }}</b></p>
            @endif
            @if(count($additionalItems))
            <p class="mt-2">Payment breakdown:</p>
            <ul class="list-disc ml-6">
                <li>Base Fee: {{ money($baseAmount, $record->currency, true)->formatWithoutZeroes() }}</li>
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
            <p>Thank you for your payment. We look forward to your participation and wish you a successful and enjoyable conference experience.</p>
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
