<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyUserEmail extends TemplateMailable
{
    public string $userFullName;

    public string $verificationUrl;

    public Log $log;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->userFullName = $user->full_name;
        $this->verificationUrl = $this->verificationUrl($user);

        $this->log = Log::make(
            name: 'email',
            subject: $user,
            description: __('general.email_sent', ['name' => 'Verify user Email']),
        );
    }

    protected function verificationUrl($user)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
    }

    public static function getDefaultSubject(): string
    {
        return 'Verify Email Address';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
        <p>Please click the button below to verify your email address.</p>
        <p><a href="{{ verificationUrl }}">Verify Email Address</a>.</p>
        <p>If you did not create an account, no further action is required.</p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent to a new registered user to validate their email account.';
    }
}
