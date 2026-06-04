<?php

namespace App\Frontend\Website\Pages;

use App\Mail\Templates\ResetPasswordMail;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;
use Livewire\Attributes\Locked;

class ResetPassword extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithFormActions;
    use InteractsWithForms;
    use WithRateLimiting;

    protected static string $view = 'frontend.website.pages.reset-password';

    protected static string $layout = 'frontend.scheduledConference.components.layout.simple-with-platform-footer';

    public ?string $email = null;

    #[Locked]
    public bool $success = false;

    public function mount()
    {
        if (auth()->check()) {
            $this->redirect($this->getRedirectUrl(), navigate: false);
        }
    }

    public static function getLayout(): string
    {
        return static::$layout;
    }

    /**
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [static::class];
    }

    public function getTitle(): string|Htmlable
    {
        return __('general.reset_password');
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getAuthLogoUrl(): string
    {
        return app()->getCurrentScheduledConference()
            ?->getFirstMedia('logo')
            ?->getAvailableUrl(['thumb', 'thumb-xl'])
            ?? app()->getCurrentConference()
                ?->getFirstMedia('logo')
                ?->getAvailableUrl(['thumb', 'thumb-xl'])
            ?? Vite::asset('resources/assets/images/logo.png');
    }

    public function getAuthLogoAltText(): string
    {
        return app()->getCurrentScheduledConference()?->title
            ?? app()->getCurrentConference()?->name
            ?? config('app.name', 'Leconfe');
    }

    public function getAuthLogoHomeUrl(): string
    {
        return app()->getCurrentScheduledConference()?->getHomeUrl()
            ?? app()->getCurrentConference()?->getHomeUrl()
            ?? url('/');
    }

    public function getRedirectUrl(): string
    {
        return route('filament.administration.home');
    }

    public function getViewData(): array
    {
        return [
            'registerUrl' => null,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/') => __('general.home'),
            __('general.reset_password'),
        ];
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->email()
                            ->required()
                            ->autofocus()
                            ->autocomplete('username')
                            ->columnSpanFull(),
                    ]),
            ),
        ];
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSubmitFormAction(),
        ];
    }

    protected function getSubmitFormAction(): Action
    {
        return Action::make('submit')
            ->label(__('general.reset_password'))
            ->submit('submit');
    }

    public function areFormActionsSticky(): bool
    {
        return false;
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::Start;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        return [];
    }

    public function rules()
    {
        return [
            'email' => 'required|email|exists:users',
        ];
    }

    public function submit()
    {
        try {
            $this->rateLimit(5, 300, 'submit');
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', __('general.throttle_to_many_reset_password_attempts', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => ceil($exception->secondsUntilAvailable / 60),
            ]));

            return null;
        }

        $this->validate();

        $user = User::where('email', $this->email)->first();

        Mail::to($this->email)
            ->send(new ResetPasswordMail($user));

        $this->success = true;
    }
}
