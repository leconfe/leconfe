<?php

namespace App\Frontend\Website\Pages;

use App\Actions\UserInvitation\AcceptUserInvitationAction;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class InvitationAccept extends Page
{
    protected static string $view = 'frontend.website.pages.invitation-accept';

    public ?string $errorMessage = null;
    public ?string $nextUrl = null;

    public function mount(string $token): void
    {
        $invitation = $this->getValidInvitation($token);
        if (! auth()->check()) {
            $invitedUserExists = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])
                ->exists();

            $targetUrl = $invitedUserExists
                ? $this->getLoginUrl($invitation)
                : $invitation->getRegisterUrl();

            $this->nextUrl = $targetUrl;
            $this->redirect($targetUrl, navigate: false);

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            $this->errorMessage = 'You are logged in with a different email account.';

            return;
        }

        AcceptUserInvitationAction::run($invitation, $user);

        $this->nextUrl = $this->getSuccessUrl($invitation);
        $this->redirect($this->nextUrl, navigate: false);

        return;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Accept Invitation';
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/invitations/{token}/accept', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }

    protected function getViewData(): array
    {
        return [
            'errorMessage' => $this->errorMessage,
            'loginUrl' => app()->getLoginUrl(),
            'nextUrl' => $this->nextUrl,
        ];
    }

    protected function getValidInvitation(string $token): UserInvitation
    {
        $invitation = UserInvitation::query()
            ->with(['conference', 'scheduledConference'])
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->status !== 'pending') {
            abort(410, 'Invitation is no longer active.');
        }

        if ($invitation->expires_at?->isPast()) {
            $invitation->update(['status' => 'expired']);
            abort(410, 'Invitation has expired.');
        }

        return $invitation;
    }

    protected function getLoginUrl(UserInvitation $invitation): string
    {
        if ($invitation->scheduledConference && $invitation->conference) {
            return route('livewirePageGroup.scheduledConference.pages.login', [
                'conference' => $invitation->conference->path,
                'serie' => $invitation->scheduledConference->path,
            ]);
        }

        if ($invitation->conference) {
            return route('livewirePageGroup.conference.pages.login', [
                'conference' => $invitation->conference->path,
            ]);
        }

        return route('livewirePageGroup.website.pages.login');
    }

    protected function getSuccessUrl(UserInvitation $invitation): string
    {
        if ($invitation->scheduledConference) {
            return $invitation->scheduledConference->getPanelUrl();
        }

        if ($invitation->conference) {
            return $invitation->conference->getPanelUrl();
        }

        return route('filament.administration.home');
    }
}
