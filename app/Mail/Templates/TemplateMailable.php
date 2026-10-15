<?php

namespace App\Mail\Templates;

use App\Models\MailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Mustache_Engine;
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

    /**
     * Variables whose value is a translated model attribute, resolved when the email
     * is rendered so it follows the recipient's language.
     *
     * @var array<string, array{0: class-string<Model>, 1: mixed, 2: string}>
     */
    protected array $translatableData = [];

    public static function getDefaultTextTemplate(): string
    {
        return MailTemplate::textFromHtml(static::getDefaultHtmlTemplate());
    }

    public static function getVariables(): array
    {
        return array_merge(static::getConferenceViewData(), parent::getVariables());
    }

    public function buildViewData(): array
    {
        return array_merge(static::getConferenceViewData(), parent::buildViewData(), $this->resolveTranslatableData());
    }

    /**
     * Use the translated value of a model attribute for a template variable.
     */
    public function setTranslatableData(string $variable, ?Model $model, string $attribute): static
    {
        if ($model && method_exists($model, 'translated')) {
            $this->translatableData[$variable] = [$model::class, $model->getKey(), $attribute];
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveTranslatableData(): array
    {
        $data = [];

        foreach ($this->translatableData as $variable => [$class, $key, $attribute]) {
            $value = $class::query()->withoutGlobalScopes()->find($key)?->translated($attribute);

            if (filled($value)) {
                $data[$variable] = $value;
            }
        }

        return $data;
    }

    public static function getConferenceViewData()
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        if ($scheduledConference) {
            $logo = $scheduledConference->getFirstMedia('logo')
                ?? $scheduledConference->conference?->getFirstMedia('logo');

            $title = $scheduledConference->translated('title');

            return [
                'conferenceName' => $title,
                'conferenceLink' => $scheduledConference->getHomeUrl(),
                'conferenceLogoUrl' => $logo?->getAvailableUrl(['thumb', 'thumb-xl']),
                'conferenceLogoAltText' => $title,
            ];
        }

        $conference = app()->getCurrentConference();

        if (! $conference) {
            return [];
        }

        $name = $conference->translated('name');

        return [
            'conferenceName' => $name,
            'conferenceLink' => $conference->getHomeUrl(),
            'conferenceLogoUrl' => $conference->getFirstMedia('logo')?->getAvailableUrl(['thumb', 'thumb-xl']),
            'conferenceLogoAltText' => $name,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                app()->getCurrentScheduledConference()?->translated('title')
                    ?? app()->getCurrentConference()?->translated('name')
                    ?? app()->getSite()->translated('name')
            ),
        );
    }

    public function htmlTemplate($html)
    {
        $this->html_template = $html;

        return $this;
    }

    protected function buildSubject($message)
    {
        if ($this->subject) {
            $message->subject(app(Mustache_Engine::class)->render($this->subject, $this->buildViewData()));

            return $this;
        }

        if ($this->getMailTemplate()->getSubject()) {
            $subject = $this
                ->getMailTemplateRenderer()
                ->renderSubject($this->buildViewData());

            $message->subject($subject);

            return $this;
        }

        return parent::buildSubject($message);
    }
}
