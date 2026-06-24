<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\UserInvitation;
use Carbon\Carbon;

class UserRoleInvitationMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(UserInvitation $invitation)
    {
        $conferenceTitle = $invitation->scheduledConference?->title
            ?? $invitation->scheduledConference?->conference?->name
            ?? $invitation->conference?->name
            ?? app()->getCurrentConference()?->name
            ?? app()->getSite()->getMeta('name');

        $scheduledConferenceTitle = $invitation->scheduledConference?->title;

        $this->setAdditionalData([
            'Invitation Email' => $invitation->email,
            'Role Name' => $invitation->role_name,
            'Conference Title' => $conferenceTitle,
            'Scheduled Conference Title' => $scheduledConferenceTitle ?: '-',
            'Invitation URL' => $invitation->getAcceptUrl(),
            'Invitation Expiration Date' => Carbon::parse($invitation->expires_at)->format('d F Y H:i'),
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $invitation,
            description: __('general.email_sent', ['name' => 'User Role Invitation']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'You are invited to join as {{ Role Name }}';
    }

    public static function getDefaultDescription(): string
    {
        return 'This email is sent when a user is invited to an internal role.';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Hello,</p>

            <p>You have been invited to join <b>{{ Conference Title }}</b> as <b>{{ Role Name }}</b>.</p>

            <p>
                Use this link to accept the invitation:
                <a href="{{ Invitation URL }}" target="_blank" rel="noopener noreferrer">{{ Invitation URL }}</a>
            </p>

            <p>This invitation expires on {{ Invitation Expiration Date }}.</p>
        HTML;
    }
}
