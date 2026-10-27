<?php

namespace Tests\Feature\Multilingual;

use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use Filament\Forms\Components\Radio;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class UserRoleLabelTranslationTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    public function test_role_labels_follow_the_interface_language_while_values_and_assignments_stay(): void
    {
        $conference = $this->makeConference();
        $scheduledConference = $this->makeScheduledConference($conference);
        $this->useConference($conference, $scheduledConference);

        $role = Role::query()->where('name', UserRole::TrackEditor->value)->firstOrFail();
        $user = User::query()->create(['given_name' => 'Tester', 'email' => 'labels@example.test', 'password' => 'password123456']);
        $user->assignRole(UserRole::TrackEditor);

        app()->setLocale('fr');
        $this->assertSame('Responsable d’axe thématique', $role->display_name);
        $this->assertSame('Responsable d’axe thématique', UserRole::displayLabel('Track Editor'));
        $this->assertSame('Track Editor', $role->fresh()->name);
        $this->assertTrue($user->hasRole(UserRole::TrackEditor));
        $this->assertSame('Organizer custom role', (new Role(['name' => 'Organizer custom role']))->display_name);

        app()->setLocale('en');
        $this->assertSame('Track Editor', $role->display_name);
    }

    public function test_make_a_submission_lists_french_track_and_role_options_with_their_ids(): void
    {
        $conference = $this->makeConference();
        $scheduledConference = $this->makeScheduledConference($conference);
        $this->useConference($conference, $scheduledConference);

        $track = Track::query()->firstOrFail();
        $role = Role::query()->where('name', UserRole::Author->value)->firstOrFail();
        Permission::findOrCreate('Submission:submitAs', 'web');
        $user = User::query()->create(['given_name' => 'Author', 'email' => 'create-options@example.test', 'password' => 'password123456']);
        $user->assignRole(UserRole::Author);
        $this->actingAs($user);

        app()->setLocale('fr');
        $page = new CreateSubmission;
        $radios = collect($page->form(Form::make($page))->getFlatComponents(true))
            ->filter(fn ($component) => $component instanceof Radio)
            ->mapWithKeys(fn (Radio $component) => [$component->getName() => $component]);

        $this->assertSame([$track->getKey() => 'Axe thématique général'], $radios['track_id']->getOptions());
        $this->assertSame([$role->getKey() => 'Auteur'], $radios['user_group_id']->getOptions());
    }
}
