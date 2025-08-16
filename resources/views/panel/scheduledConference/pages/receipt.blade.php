<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>
        Receipt - {{ $record->invoice }} - {{ $scheduledConference->title }}
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
            {!! $scheduledConference->getMeta('invoice_sender_information') !!}
        </div>
        <div class="bg-gray-200 p-2 mt-4">
            <p class="text-xl font-bold">Invoice No.: {{ $record->invoice }}</p>
             <p>Registration Date: {{ $record->created_at->format('jS M Y, h:i:sa') }} </p>
        </div>
        <div class="mt-8 text-base">
            <p>Dear {{ $user_fullname }},</p>
            <p>
                We have successfully received your registration fee of {{ $record->getFormattedFee() }}.
            </p>
            <p>Thank you for your payment. We look forward to your participation and wish you a successful and enjoyable conference experience.</p>
        </div>
        <div class="mt-8 font-bold">
            <p>With best regards,</p>
            <p>{{ $scheduledConference->getMeta('organizer') }}</p>
        </div>
    </div>
</body>

</html>
