<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\ScheduledConference;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class ResetPasswordMail extends TemplateMailable
{
    public string $siteName;

    public string $verificationUrl;

    public Log $log;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $siteModel = app()->getCurrentScheduledConference() ?? app()->getCurrentConference() ?? app()->getSite();
        $siteAttribute = $siteModel instanceof ScheduledConference ? 'title' : 'name';

        $this->siteName = (string) $siteModel->translated($siteAttribute);
        $this->setTranslatableData('siteName', $siteModel, $siteAttribute);

        $this->verificationUrl = $this->verificationUrl($user);

        $this->log = Log::make(
            name: 'email',
            subject: $user,
            description: __('general.email_sent', ['name' => 'Reset user password']),
        )->by(auth()->user());
    }

    protected function verificationUrl($user)
    {
        $routeName = 'livewirePageGroup.website.pages.reset-password-confirmation';

        if (app()->getCurrentConference()) {
            $routeName = 'livewirePageGroup.conference.pages.reset-password-confirmation';
        }

        if (app()->getCurrentScheduledConference()) {
            $routeName = 'livewirePageGroup.scheduledConference.pages.reset-password-confirmation';
        }

        return URL::temporarySignedRoute(
            $routeName,
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'user' => $user->email,
                'hash' => sha1($user->email.$user->password.$user->getMeta('last_login')),
            ]
        );
    }

    public static function getDefaultSubject(): string
    {
        return __('mail_templates.reset_password.subject');
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return __('mail_templates.reset_password.body');
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is for resetting user password.';
    }
}
