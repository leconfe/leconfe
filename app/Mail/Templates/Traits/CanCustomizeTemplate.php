<?php

namespace App\Mail\Templates\Traits;

use App\Facades\Setting;
use App\Models\MailTemplate;
use Illuminate\Contracts\Mail\Mailable;
use Spatie\MailTemplates\Interfaces\MailTemplateInterface;

trait CanCustomizeTemplate
{
    public ?string $customizedContent = null;

    public ?string $customizedSubject = null;

    abstract protected function resolveTemplateModel(): MailTemplateInterface;

    public function contentUsing(?string $customizedContent): static
    {
        $this->customizedContent = $customizedContent;

        return $this;
    }

    public function subjectUsing(?string $customizedSubject): static
    {
        $this->customizedSubject = $customizedSubject;

        return $this;
    }

    public function getMailTemplate(): MailTemplateInterface
    {
        if ($this->customizedContent) {
            return new class($this->customizedContent, $this->customizedSubject) implements MailTemplateInterface
            {
                public function __construct(public ?string $content, public ?string $subject) {}

                public function getSubject(): string
                {
                    return $this->subject;
                }

                public function getHtmlTemplate(): string
                {
                    return $this->content;
                }

                public static function findForMailable(Mailable $mailable)
                {
                    return null;
                }

                public function getTextTemplate(): ?string
                {
                    return null;
                }

                public function getHtmlLayout(): string
                {
                    return view('mail.template', [
                        'body' => '{{{ body }}}',
                        'header' => Setting::get('mail_header') ?? MailTemplate::getDefaultHeader(),
                        'footer' => Setting::get('mail_footer') ?? MailTemplate::getDefaultFooter(),
                    ])->render();
                }
            };
        }

        return $this->resolveTemplateModel();
    }
}
