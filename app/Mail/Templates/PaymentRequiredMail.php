<?php

namespace App\Mail\Templates;

use App\Models\Payment;

class PaymentRequiredMail extends TemplateMailable
{
    public string $title;

    public string $scheduledConferenceTitle;

    public string $paymentLink;

    public string $paymentName;

    public string $type;

    public string $description;

    public string $fee;

    public function __construct(Payment $payment)
    {
        $this->title = $payment->getMeta('title');

        $this->paymentLink = $payment->getPaymentUrl();

        $this->scheduledConferenceTitle = $payment->scheduledConference->title;

        $this->paymentName = $payment->fee->name;

        $this->description = $payment->getMeta('description') ?? '-';

        $this->fee = $payment->getFormattedFee();
    }

    public static function getDefaultSubject(): string
    {
        return 'Payment Required: {{ title }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>You have due payment for : </p>
            <table>
                <tr>
                    <td style="width:200px;vertical-align: top">Submission Title</td>
                    <td style="vertical-align: top">:</td>
                    <td>{{ title }}</td>
                </tr>
                <tr>
                    <td style="width:200px;">Payment Name</td>
                    <td>:</td>
                    <td>{{ paymentName }}</td>
                </tr>
                <tr>
                    <td style="width:200px;">Fee</td>
                    <td>:</td>
                    <td>{{ fee }}</td>
                </tr>
                <tr>
                    <td style="width:200px;vertical-align: top;" >Description</td>
                    <td style="vertical-align: top">:</td>
                    <td>{{ description }}</td>
                </tr>
            </table>
            <p>
                Link: <a href="{{ paymentLink }}">{{ paymentLink }}</a>
            </p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'Payment required email template';
    }
}
