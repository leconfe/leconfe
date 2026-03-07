<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Actions\User\UserCreateAction;
use App\Frontend\Website\Pages\Page;
use App\Models\Enums\UserRole;
use App\Panel\ScheduledConference\Pages\Dashboard;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Squire\Models\Country;

class Register extends Page
{
    use WithRateLimiting;

    protected static string $view = 'frontend.website.pages.register';

    public $given_name = null;

    public $family_name = null;

    public $public_name = null;

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

            return;
        }

        $this->country = app()->getCurrentScheduledConference()->getMeta('default_register_country');
    }

    public function getTitle(): string|Htmlable
    {
        return $this->registerComplete ? __('general.registration_complete') : __('general.register');
    }

    public function rules()
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        $rules = [
            'given_name' => [
                $scheduledConference->getMeta('required_given_name') ? 'required' : 'nullable',
            ],
            'family_name' => [
                $scheduledConference->getMeta('required_family_name') ? 'required' : 'nullable',
            ],
            'public_name' => [
                $scheduledConference->getMeta('required_public_name') ? 'required' : 'nullable',
            ],
            'affiliation' => [
                $scheduledConference->getMeta('required_affiliation') ? 'required' : 'nullable',
            ],
            'country' => [
                $scheduledConference->getMeta('required_country') ? 'required' : 'nullable',
            ],
            'phone' => [
                $scheduledConference->getMeta('required_phone') ? 'required' : 'nullable',
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
            'required',
        ];

        return $rules;
    }

    public function getRedirectUrl(): string
    {
        return route(Dashboard::getRouteName('scheduledConference'));
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

        $allowedRoles = array_values(UserRole::getAllowedSelfAssignRoleNames());

        // Filter only allowed roles to register
        $selfAssignRoles = collect($data['selfAssignRoles'])
            ->filter(fn ($role) => in_array($role, $allowedRoles))
            ->toArray();

        try {
            DB::beginTransaction();
            $user = UserCreateAction::run([
                ...Arr::only($data, ['given_name', 'family_name', 'email', 'password']),
                'meta' => Arr::only($data, ['affiliation', 'country', 'phone', 'public_name']),
            ]);

            if (app()->getCurrentConference()) {
                $user->assignRole($selfAssignRoles);
            } else {
                foreach ($selfAssignRoles as $conferenceId => $roles) {
                    // get keys of roles where value is true
                    $roles = array_keys(array_filter($roles));
                    $user->assignRole($roles);
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        if (config('app.must_verify_email')) {
            $user->sendEmailVerificationNotification();
        }

        Filament::auth()->login($user);

        session()->regenerate();

        $this->registerComplete = true;
    }

    protected function getViewData(): array
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        $data = [
            'countries' => Country::all(),
            'roles' => UserRole::getAllowedSelfAssignRoleNames(),
            'loginUrl' => app()->getLoginUrl(),
            'allowRegistration' => $scheduledConference->getMeta('allow_registration'),
            'scheduledConference' => $scheduledConference,
            'privacyStatementUrl' => route(PrivacyStatement::getRouteName()),
            'requiredFields' => [
                'given_name' => $scheduledConference->getMeta('required_given_name'),
                'family_name' => $scheduledConference->getMeta('required_family_name'),
                'public_name' => $scheduledConference->getMeta('required_public_name'),
                'affiliation' => $scheduledConference->getMeta('required_affiliation'),
                'country' => $scheduledConference->getMeta('required_country'),
                'phone' => $scheduledConference->getMeta('required_phone'),
            ],
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
