<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>
        Invoice - {{ $record->invoice }} - {{ $scheduledConference->title }}
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
        <img class="max-w-72"
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
        <div class="mt-4">
            <p class="font-bold text-base">Invoiced To</p>
            <p>{{ $user_affiliation }}</p>
            <p>{{ $user_fullname }}</p>
            <p>{{ $user_address_line }}</p>
            <p>{{ $user_city }} {{ $user_post_code }}</p>
            <p>{{ $user_country_name }}</p>
        </div>
        <div class="mt-4 invoice-table p-1">
            <table class="w-full border border-collapse border-gray-400">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border p-2 border-gray-400 w-[70%]">Description</th>
                        <th class="border p-2 border-gray-400">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border p-2 border-gray-400 align-middle">
                            <p>{{ $record->fee->name }}</p>
                            <p class="font-bold text-base">{{ $scheduledConference->title }}</p>
                            <div>&nbsp;</div>
                        </td>
                        <td class="border p-2 border-gray-400 align-top text-center">
                            {{ $record->getFormattedFee() }}
                            <div>&nbsp;</div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-200">
                        <td class="border p-2 border-gray-400 font-bold text-right ">Total</td>
                        <td class="border p-2 border-gray-400 font-bold text-center">{{ $record->getFormattedFee() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="mt-4 max-w-none prose prose-sm prose-p:my-0 prose-p:leading-5 prose-li:leading-5 prose-ol:mt-0" style="--tw-prose-body: #000;--tw-prose-counters: #000;">
            {!! $scheduledConference->getMeta('invoice_notes') !!}
        </div>
        <div class="mt-8 font-bold">
            <p>With best regards,</p>
            <p>{{ $scheduledConference->getMeta('organizer') }}</p>
        </div>
    </div>
</body>

</html>
