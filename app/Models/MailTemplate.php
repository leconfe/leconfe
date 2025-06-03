<?php

namespace App\Models;

use App\Facades\Setting;
use Illuminate\Contracts\Mail\Mailable;
use Spatie\MailTemplates\Interfaces\MailTemplateInterface;
use Spatie\MailTemplates\Models\MailTemplate as BaseMailTemplate;

class MailTemplate extends BaseMailTemplate implements MailTemplateInterface
{
    public function getHtmlLayout(): string
    {
        return view('mail.template', [
            'body' => '{{{ body }}}',
            'header' => Setting::get('mail_header') ?? static::getDefaultHeader(),
            'footer' => Setting::get('mail_footer') ?? static::getDefaultFooter(),
        ])->render();
    }

    protected static function booted(): void
    {
        static::updating(function (MailTemplate $model) {
            $model->text_template = preg_replace("/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($model->html_template))));
        });
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
