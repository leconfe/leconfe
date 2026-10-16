<?php

namespace Tests\Feature\Multilingual;

use App\Frontend\ScheduledConference\Pages\Register;
use App\Frontend\Website\Pages\InvitationRegister;
use App\Frontend\Website\Pages\Login;
use App\Livewire\LanguageSwitcher;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Panel\Administration\Pages\Profile;
use App\Panel\Conference\Resources\UserResource\Pages\EditUser;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Rahmanramsi\LivewirePageGroup\Facades\LivewirePageGroup;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class PreferredLanguageTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->getSite();
        $this->enableLanguages(['en', 'id']);
        Mail::fake();
    }

    public function test_scheduled_conference_registration_stores_the_active_locale(): void
    {
        $conference = $this->makeConference();
        $scheduledConference = $this->makeScheduledConference($conference);
        $scheduledConference->setMeta('allow_registration', true);
        $this->useConference($conference, $scheduledConference);
        LivewirePageGroup::setCurrentPageGroup(LivewirePageGroup::getPageGroup('scheduledConference'));

        App::setLocale('id');

        Livewire::test(Register::class)
            ->set('given_name', 'Rina')
            ->set('family_name', 'Author')
            ->set('email', 'rina@example.com')
            ->set('password', 'password123456')
            ->set('password_confirmation', 'password123456')
            ->set('privacy_statement_agree', true)
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame('id', User::query()->where('email', 'rina@example.com')->value('locale'));
    }

    public function test_invitation_registration_stores_the_active_locale(): void
    {
        $conference = $this->makeConference();

        Role::withoutGlobalScopes()->firstOrCreate([
            'name' => 'Reviewer',
            'guard_name' => 'web',
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => 0,
        ]);

        $invitation = UserInvitation::query()->create([
            'email' => 'invitee@example.com',
            'role_name' => 'Reviewer',
            'token' => 'preferred-language-invitation',
            'status' => 'pending',
            'conference_id' => $conference->getKey(),
        ]);

        App::setLocale('id');

        Livewire::test(InvitationRegister::class, ['token' => $invitation->token])
            ->set('given_name', 'Invitee')
            ->set('family_name', 'User')
            ->set('password', 'password12345')
            ->set('password_confirmation', 'password12345')
            ->set('privacy_statement_agree', true)
            ->call('register');

        $this->assertSame('id', User::query()->where('email', 'invitee@example.com')->value('locale'));
    }

    public function test_profile_saves_an_enabled_language_and_rejects_a_disabled_one(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));

        Livewire::test(Profile::class)
            ->assertFormFieldIsVisible('locale', 'informationForm')
            ->set('informationFormData.locale', 'id')
            ->call('submitInformationForm')
            ->assertHasNoErrors();

        $this->assertSame('id', $user->refresh()->locale);

        Livewire::test(Profile::class)
            ->assertSet('informationFormData.locale', 'id')
            ->set('informationFormData.locale', 'fr')
            ->call('submitInformationForm')
            ->assertHasErrors(['informationFormData.locale']);

        $this->assertSame('id', $user->refresh()->locale);
    }

    public function test_saving_the_profile_does_not_change_the_session_language(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));
        session()->put('locale', 'en');

        Livewire::test(Profile::class)
            ->set('informationFormData.locale', 'id')
            ->call('submitInformationForm');

        $this->assertSame('en', session('locale'));
    }

    public function test_language_select_is_hidden_with_a_single_language(): void
    {
        $this->enableLanguages(['en']);

        $user = $this->makeUser(['locale' => 'en']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));

        Livewire::test(Profile::class)
            ->assertFormFieldIsHidden('locale', 'informationForm')
            ->set('informationFormData.given_name', 'Renamed')
            ->call('submitInformationForm')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->given_name);
        $this->assertSame('en', $user->locale);
    }

    public function test_administrator_can_change_a_users_language(): void
    {
        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));

        $this->actingAs($this->makeUser(['email' => 'admin@example.com']));
        $user = $this->makeUser(['email' => 'member@example.com']);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertFormFieldIsVisible('locale')
            ->fillForm(['locale' => 'id'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('id', $user->refresh()->locale);
    }

    public function test_language_switch_updates_the_preference_of_a_logged_in_user(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(LanguageSwitcher::class)->call('switchLanguage', 'id');

        $this->assertSame('id', $user->refresh()->locale);

        $this->withHeader('Referer', url('/'))
            ->get(route('livewirePageGroup.website.pages.set-locale', ['lang' => 'en']))
            ->assertSessionHas('locale', 'en');

        $this->assertSame('en', $user->refresh()->locale);

        $this->get(route('livewirePageGroup.website.pages.set-locale', ['lang' => 'fr']));

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_language_switch_by_a_guest_stores_no_preference(): void
    {
        $user = $this->makeUser();

        $this->get(route('livewirePageGroup.website.pages.set-locale', ['lang' => 'id']))
            ->assertSessionHas('locale', 'id');

        Livewire::test(LanguageSwitcher::class)->call('switchLanguage', 'id');

        $this->assertNull($user->refresh()->locale);
    }

    public function test_login_applies_the_preferred_language_to_the_session(): void
    {
        $this->makeUser(['email' => 'reader@example.com', 'locale' => 'id']);

        Livewire::test(Login::class)
            ->set('email', 'reader@example.com')
            ->set('password', 'password123456')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertSame('id', session('locale'));
        $this->assertSame('id', app()->getLocale());
    }

    public function test_login_keeps_the_session_language_without_a_usable_preference(): void
    {
        $this->makeUser(['email' => 'none@example.com', 'locale' => null]);
        $this->makeUser(['email' => 'disabled@example.com', 'locale' => 'fr']);

        foreach (['none@example.com', 'disabled@example.com'] as $email) {
            session()->put('locale', 'en');

            Livewire::test(Login::class)
                ->set('email', $email)
                ->set('password', 'password123456')
                ->call('login')
                ->assertHasNoErrors();

            $this->assertSame('en', session('locale'));

            auth()->logout();
        }
    }

    public function test_preferred_locale_falls_back_to_the_site_default_language(): void
    {
        $this->assertSame('id', $this->makeUser(['email' => 'a@example.com', 'locale' => 'id'])->preferredLocale());
        $this->assertSame('en', $this->makeUser(['email' => 'b@example.com', 'locale' => null])->preferredLocale());
        $this->assertSame('en', $this->makeUser(['email' => 'c@example.com', 'locale' => 'fr'])->preferredLocale());

        $this->enableLanguages(['en', 'id'], 'id');

        $this->assertSame('id', User::query()->where('email', 'b@example.com')->first()->preferredLocale());
        $this->assertSame('id', User::query()->where('email', 'c@example.com')->first()->preferredLocale());

        $this->enableLanguages(['en']);

        $this->assertSame('en', User::query()->where('email', 'a@example.com')->first()->preferredLocale());
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::query()->create([
            'given_name' => 'Test',
            'family_name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password123456',
            ...$attributes,
        ]);
    }
}
