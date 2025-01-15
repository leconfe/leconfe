<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Frontend\ScheduledConference\Pages\Payment;
use App\Models\PaymentQueue;
use App\Models\Submission;

class PaymentRequiredMail extends TemplateMailable
{
    public string $title;

    public string $scheduledConferenceTitle;

    public string $paymentLink;

    public string $type;
    
    public string $description;

    public function __construct(PaymentQueue $paymentQueue)
    {
        $this->title = $paymentQueue->getMeta('title');

        $this->paymentLink = $paymentQueue->getPaymentUrl();

        $this->scheduledConferenceTitle = $paymentQueue->scheduledConference->title;

        $this->type = $paymentQueue->getPaymentType();

        $this->description = $paymentQueue->getMeta('description') ?? '-';
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
