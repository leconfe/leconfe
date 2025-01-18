<?php

namespace App\Mail\Templates;

use App\Models\Payment;

class PaymentRequiredMail extends TemplateMailable
{
    public string $title;

    public string $scheduledConferenceTitle;

    public string $paymentLink;

    public string $type;
    
    public string $description;
    
    public string $fee;


    public function __construct(Payment $payment)
    {
        $this->title = $payment->getMeta('title');

        $this->paymentLink = $payment->getPaymentUrl();

        $this->scheduledConferenceTitle = $payment->scheduledConference->title;

        $this->type = $payment->getPaymentType();

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
                    <td style="width:100px;">Title</td>
                    <td>:</td>
                    <td>{{ title }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Payment Type</td>
                    <td>:</td>
                    <td>{{ type }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Fee</td>
                    <td>:</td>
                    <td>{{ fee }}</td>
                </tr>
                <tr>
                    <td style="width:100px;">Description</td>
                    <td>:</td>
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
