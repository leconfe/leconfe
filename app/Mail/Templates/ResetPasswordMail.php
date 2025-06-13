<?php

namespace App\Mail\Templates;

use App\Classes\Log;
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
        $this->siteName = app()->getCurrentScheduledConference()?->title ?? app()->getSite()->getMeta('name');

        $this->verificationUrl = $this->verificationUrl($user);

        $this->log = Log::make(
            name: 'email',
            subject: $user,
            description: __('general.email_sent', ['name' => 'Reset user password']),
        );
    }

    protected function verificationUrl($user)
    {
        $routeName = 'livewirePageGroup.website.pages.reset-password-confirmation';

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
        return 'Password Reset Confirmation';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear User,</p>
            <p>We have received a request to reset your password for {{ siteName }} website. Please click the link below to reset your password:</p>
            <a href="{{ verificationUrl }}">Reset Password</a>
            <p>If you did not request a password reset, please ignore this email.</p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is for resetting user password.';
    }
}
