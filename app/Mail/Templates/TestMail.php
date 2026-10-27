<?php

namespace App\Mail\Templates;

class TestMail extends TemplateMailable
{
    public static function getDefaultSubject(): string
    {
        return __('mail_templates.test.subject');
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.test.body');
    }

    public static function getDefaultDescription(): string
    {
        return 'Email template for testing purposes';
    }
}
