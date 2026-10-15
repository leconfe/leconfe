<?php

namespace App\Models;

use App\Facades\Setting;
use App\Models\Concerns\BelongsToConference;
use App\Models\Concerns\HasTranslatableContent;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Contracts\Mail\Mailable;
use Spatie\MailTemplates\Interfaces\MailTemplateInterface;
use Spatie\MailTemplates\Models\MailTemplate as BaseMailTemplate;

class MailTemplate extends BaseMailTemplate implements MailTemplateInterface
{
    use BelongsToConference, HasTranslatableContent;

    /**
     * Columns whose content can be translated.
     *
     * @var array<int, string>
     */
    protected array $translatableColumns = ['subject', 'html_template'];

    public function getHtmlLayout(): string
    {
        return static::renderLayout();
    }

    /**
     * The email layout with the header and footer in the active language.
     */
    public static function renderLayout(): string
    {
        return view('mail.template', [
            'body' => '{{{ body }}}',
            'header' => static::layoutSetting('mail_header') ?? static::getDefaultHeader(),
            'footer' => static::layoutSetting('mail_footer') ?? static::getDefaultFooter(),
        ])->render();
    }

    /**
     * A header or footer setting in the active language, falling back to the default
     * language and then to the stored setting.
     */
    protected static function layoutSetting(string $key): ?string
    {
        $conference = app()->getCurrentConference();

        if ($conference) {
            return $conference->translated("settings_{$key}");
        }

        return Setting::get($key);
    }

    protected static function booted(): void
    {
        static::saving(function (MailTemplate $model) {
            $model->text_template = static::textFromHtml($model->html_template);
        });
    }

    /**
     * The plain-text version of an html template.
     */
    public static function textFromHtml(?string $html): string
    {
        return preg_replace("/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags((string) $html))));
    }

    public function getSubject(): string
    {
        return (string) $this->translated('subject');
    }

    public function getHtmlTemplate(): string
    {
        return (string) $this->translated('html_template');
    }

    public function getTextTemplate(): ?string
    {
        return static::textFromHtml($this->getHtmlTemplate());
    }

    public static function findForMailable(Mailable $mailable): self
    {
        $mailTemplate = static::forMailable($mailable)->first();
        if (! $mailTemplate) {
            $mailTemplate = new static([
                'mailable' => get_class($mailable),
                'subject' => $mailable::getDefaultSubject(),
                'html_template' => $mailable::getDefaultHtmlTemplate(),
                'text_template' => $mailable::getDefaultTextTemplate(),
            ]);
        }

        return $mailTemplate;
    }

    /**
     * The subject and body of a mailable's template in a language (the site default
     * language when none is given): the current conference's customized template, or
     * the mailable's built-in default.
     *
     * @param  class-string<\App\Mail\Templates\TemplateMailable>  $mailable
     * @return array{subject: string, html_template: string}
     */
    public static function contentFor(string $mailable, ?string $locale = null): array
    {
        $locale ??= app(ContentLocales::class)->default();

        $template = static::query()
            ->where('mailable', $mailable)
            ->when(app()->getCurrentConferenceId(), fn ($query, $conferenceId) => $query->where('conference_id', $conferenceId))
            ->first();

        if (! $template) {
            return [
                'subject' => $mailable::getDefaultSubject(),
                'html_template' => $mailable::getDefaultHtmlTemplate(),
            ];
        }

        return [
            'subject' => (string) $template->translated('subject', $locale),
            'html_template' => (string) $template->translated('html_template', $locale),
        ];
    }

    public static function getDefaultHeader(): string
    {
        return '';
    }

    public static function getDefaultFooter(): string
    {
        return <<<'HTML'
            <p>________________________________________________________________________</p>
            <pre>{{ conferenceName }}</pre>
            <p style="font-size: 10px;color: gray;">Easily manage conference platform using <a href="https://leconfe.com">Leconfe</a></p>
        HTML;
    }
}
