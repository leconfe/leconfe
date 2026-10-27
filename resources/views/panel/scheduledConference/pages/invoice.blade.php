<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app(\App\Support\Multilingual\ContentLocales::class)->direction() }}">

<head>
    <meta charset="UTF-8">
    <title>
        {{ __('general.invoice_document') }} - {{ $record->invoice }} - {{ $scheduledConference->title }}
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
        <img class="max-h-40"
            src="{{ $scheduledConference->getFirstMedia('logo')?->getAvailableUrl(['thumb', 'thumb-xl']) }}"
            alt="{{ $scheduledConference->title }}">
        @endif
        <div class="text-right">
            <p class="text-base font-bold">{{ $scheduledConference->title }}</p>
            {!! $scheduledConference->getMeta('invoice_sender_information') !!}
        </div>
        <div class="bg-gray-200 p-2 mt-4">
            <p class="text-xl font-bold">{{ __('general.invoice_number_value', ['number' => $record->invoice]) }}</p>
            <p>{{ __('general.document_registration_date', ['date' => $record->created_at->locale(app()->getLocale())->isoFormat('LL LT')]) }}</p>
        </div>
        <div class="mt-4">
            <p class="font-bold text-base">{{ __('general.invoiced_to') }}</p>
            <p>{{ $user_fullname }}</p>
            <p>{{ $user_affiliation }}</p>
            <p>{{ $user_address_line }}</p>
            <p>{{ $user_city }} {{ $user_post_code }}</p>
            <p>{{ $user_country_name }}</p>
        </div>
        <div class="mt-4 invoice-table p-1">
            <table class="w-full border border-collapse border-gray-400">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border p-2 border-gray-400 w-[50%]">{{ __('general.description') }}</th>
                        <th class="border p-2 border-gray-400">{{ __('general.unit_price') }}</th>
                        <th class="border p-2 border-gray-400">{{ __('general.invoice_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border p-2 border-gray-400 align-middle">
                            <p>{{ $record->fee->translated('name') }}</p>
                            @if($submissionTitle)
                            <p class="text-xs text-gray-600">{{ __('general.document_submission_title_label') }} {{ $submissionTitle }}</p>
                            @endif
                        </td>
                        <td class="border p-2 border-gray-400 align-top text-center">
                            {{ money($baseAmount, $record->currency, true)->formatWithoutZeroes() }}
                        </td>
                        <td class="border p-2 border-gray-400 align-top text-center">
                            {{ money($baseAmount, $record->currency, true)->formatWithoutZeroes() }}
                        </td>
                    </tr>
                    @foreach($additionalItems as $item)
                    <tr>
                        <td class="border p-2 border-gray-400 align-middle">
                            <p>{{ data_get($item, 'name') }}</p>
                            @if(data_get($item, 'description'))
                            <p class="text-xs text-gray-600">{{ data_get($item, 'description') }}</p>
                            @endif
                            @if(data_get($item, 'quantity', 1) > 1)
                            <p class="text-xs text-gray-600">{{ __('general.document_quantity', ['quantity' => data_get($item, 'quantity')]) }}</p>
                            @endif
                        </td>
                        <td class="border p-2 border-gray-400 align-top text-center">
                            {{ money((float) data_get($item, 'amount', 0), $record->currency, true)->formatWithoutZeroes() }}
                        </td>
                        <td class="border p-2 border-gray-400 align-top text-center">
                            {{ money((float) data_get($item, 'total_amount', data_get($item, 'amount', 0)), $record->currency, true)->formatWithoutZeroes() }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-200">
                        <td class="border p-2 border-gray-400 font-bold text-right" colspan="2">{{ __('general.invoice_total') }}</td>
                        <td class="border p-2 border-gray-400 font-bold text-center">{{ $record->getFormattedFee() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="mt-4 max-w-none prose prose-sm prose-p:my-0 prose-p:leading-5 prose-li:leading-5 prose-ol:mt-0" style="--tw-prose-body: #000;--tw-prose-counters: #000;">
            {!! $scheduledConference->getMeta('invoice_notes') !!}
        </div>
    </div>
</body>

</html>
