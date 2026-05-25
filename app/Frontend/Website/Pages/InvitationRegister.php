<?php

namespace App\Frontend\Website\Pages;

use App\Actions\UserInvitation\AcceptUserInvitationAction;
use App\Actions\User\UserCreateAction;
use App\Models\User;
use App\Models\UserInvitation;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class InvitationRegister extends Page
{
    protected static string $view = 'frontend.website.pages.invitation-register';

    public string $token;

    public ?string $given_name = null;

    public ?string $family_name = null;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public bool $privacy_statement_agree = false;

    public function mount(string $token): void
    {
        $invitation = $this->getValidInvitation($token);
        $this->token = $invitation->token;

        if (Filament::auth()->check()) {
            $this->redirect($invitation->getAcceptUrl(), navigate: false);

            return;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Invitation Registration';
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/invitations/{token}/register', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }

    public function rules(): array
    {
        return [
            'given_name' => ['required', 'string', 'max:255'],
            'family_name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'min:12'],
            'privacy_statement_agree' => ['accepted'],
        ];
    }

    public function register()
    {
        $invitation = $this->getValidInvitation($this->token);

        $this->validate();

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])
            ->first();

        if ($existingUser) {
            throw ValidationException::withMessages([
                'token' => 'An account already exists for this invitation email. Please sign in to accept the invitation.',
            ]);
        }

        $user = UserCreateAction::run([
            'given_name' => $this->given_name,
            'family_name' => $this->family_name,
            'email' => mb_strtolower($invitation->email),
            'password' => $this->password,
        ]);

        $this->markInvitationEmailAsVerified($user);

        Filament::auth()->login($user);
        session()->regenerate();

        AcceptUserInvitationAction::run($invitation, $user);

        return $this->redirect($this->getSuccessUrl($invitation), navigate: false);
    }

    protected function getViewData(): array
    {
        $invitation = $this->getInvitationForDisplay($this->token);

        return [
            'invitation' => $invitation,
            'privacyStatementUrl' => $invitation->scheduledConference && $invitation->conference
                ? route('livewirePageGroup.scheduledConference.pages.privacy-statement', [
                    'conference' => $invitation->conference->path,
                    'serie' => $invitation->scheduledConference->path,
                ])
                : null,
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

    protected function getInvitationForDisplay(string $token): UserInvitation
    {
        return UserInvitation::query()
            ->with(['conference', 'scheduledConference'])
            ->where('token', $token)
            ->firstOrFail();
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

    protected function markInvitationEmailAsVerified(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->saveQuietly();
    }
}
