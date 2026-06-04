<?php

namespace App\Frontend\Website\Pages;

use App\Models\User;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class ResetPasswordConfirmation extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string $view = 'frontend.website.pages.reset-password-confirmation';

    protected static string $layout = 'frontend.scheduledConference.components.layout.simple-with-platform-footer';

    public User $user;

    public string $hash;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    #[Locked]
    public bool $success = false;

    public function mount(User $user, string $hash, Request $request)
    {
        if (auth()->check()) {
            $this->redirect($this->getRedirectUrl(), navigate: false);
        }

        if (! $request->hasValidSignature()) {
            // Silently abort the request
            abort(403, 'Invalid or expired signature');
        }

        if ($hash !== sha1($user->email.$user->password.$user->getMeta('last_login'))) {
            abort(403, 'Invalid or expired signature');
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
        return __('general.reset_password_confirmation');
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
            __('general.login'),
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
                        TextInput::make('password')
                            ->label(__('general.new_password'))
                            ->password()
                            ->required()
                            ->autocomplete('new-password')
                            ->columnSpanFull(),
                        TextInput::make('password_confirmation')
                            ->label(__('general.confirm_password'))
                            ->password()
                            ->required()
                            ->autocomplete('new-password')
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
            ->label(__('general.submit'))
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
            'password' => ['required', 'confirmed', Password::min(12)],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    public function submit()
    {
        $data = $this->validate();

        $this->user->update([
            'password' => Hash::make($data['password']),
        ]);

        $this->success = true;
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();

        Route::get('reset-password-confirmation/{user:email}/{hash}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
