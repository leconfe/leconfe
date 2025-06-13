<?php

namespace App\Mail\Templates;

use App\Models\MailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Spatie\MailTemplates\TemplateMailable as BaseTemplateMailable;

abstract class TemplateMailable extends BaseTemplateMailable implements Interfaces\HasDefaultMailVariable, ShouldQueue
{
    use Queueable, SerializesModels;

    protected static $templateModelClass = MailTemplate::class;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    public static function getDefaultTextTemplate(): string
    {
        return preg_replace("/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags(static::getDefaultHtmlTemplate()))));
    }

    public static function getVariables(): array
    {
        return array_merge(static::getConferenceViewData(), parent::getVariables());
    }

    public function buildViewData(): array
    {
        return array_merge(static::getConferenceViewData(), parent::buildViewData());
    }

    public static function getConferenceViewData()
    {
        $scheduledConference = app()->getScheduledCurrentConference();

        if (! $scheduledConference) {
            return [];
        }

        return [
            'conferenceName' => $scheduledConference->title,
            'conferenceLink' => $scheduledConference->getHomeUrl(),
            'conferenceLogoUrl' => $scheduledConference->getFirstMedia('logo')?->getAvailableUrl(['thumb', 'thumb-xl']),
            'conferenceLogoAltText' => $scheduledConference->title,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), app()->getScheduledCurrentConference()?->title ?? app()->getSite()->getMeta('name')),
        );
    }
}
