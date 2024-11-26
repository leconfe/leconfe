<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Actions\User\UserCreateAction;
use App\Frontend\Website\Pages\Page;
use App\Models\Enums\UserRole;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Squire\Models\Country;

class Register extends Page
{
    use WithRateLimiting;

    protected static string $view = 'frontend.website.pages.register';

    public $given_name = null;

    public $family_name = null;

    public $affiliation = null;

    public $country = null;

    public $email = null;

    public $phone = null;

    public $password = null;

    public $password_confirmation = null;

    public $privacy_statement_agree = false;

    public $selfAssignRoles = [];

    public $registerComplete = false;

    public function mount()
    {
        if (Filament::auth()->check()) {
            $this->redirect($this->getRedirectUrl(), navigate: false);
        }

        $this->country = app()->getCurrentScheduledConference()->getMeta('default_register_country');
    }

    public function getTitle(): string|Htmlable
    {
        return $this->registerComplete ? __('general.registration_complete') : __('general.register');
    }

    public function rules()
    {
        $rules = [
            'given_name' => [
                'required',
            ],
            'family_name' => [
                'nullable',
            ],
            'affiliation' => [
                'nullable',
            ],
            'country' => [
                'nullable',
            ],
            'phone' => [
                'nullable',
                'phone:INTERNATIONAL',
            ],
            'email' => [
                'required',
                'email',
                'indisposable',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                'min:12',
            ],
            'privacy_statement_agree' => [
                'required',
            ],
        ];

        $rules['selfAssignRoles'] = [
            'array',
        ];

        return $rules;
    }

    public function getRedirectUrl(): string
    {
        return Filament::getPanel('scheduledConference')->getUrl();
    }

    public function register()
    {
        if (! app()->getCurrentScheduledConference()->getMeta('allow_registration')) {
            abort(403);
        }

        try {
            $this->rateLimit(5, 300);
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', __('general.throttle_to_many_register_attempts', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => ceil($exception->secondsUntilAvailable / 60),
            ]));

            return null;
        }

        $data = $this->validate();
        $user = UserCreateAction::run([
            ...Arr::only($data, ['given_name', 'family_name', 'email', 'password']),
            'meta' => Arr::only($data, ['affiliation', 'country', 'phone']),
        ]);

        if (app()->getCurrentConference()) {
            $user->assignRole($data['selfAssignRoles']);
        } else {
            foreach ($data['selfAssignRoles'] as $conferenceId => $roles) {
                // get keys of roles where value is true
                $roles = array_keys(array_filter($roles));
                $user->assignRole($roles);
            }
        }

        Filament::auth()->login($user);

        session()->regenerate();

        $this->registerComplete = true;
    }

    protected function getViewData(): array
    {
        $data = [
            'countries' => Country::all(),
            'roles' => UserRole::getAllowedSelfAssignRoleNames(),
            'loginUrl' => app()->getLoginUrl(),
            'allowRegistration' => app()->getCurrentScheduledConference()->getMeta('allow_registration'),
            'scheduledConference' => app()->getCurrentScheduledConference(),
            'privacyStatementUrl' => route(PrivacyStatement::getRouteName()),
        ];

        return $data;
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            $this->getTitle(),
        ];
    }
}
