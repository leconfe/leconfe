<?php

namespace Tests\Feature;

use App\Actions\Authors\AuthorRolePopulateDefaultDataAction;
use App\Actions\SubmissionFiles\FilesTypePopulateAction;
use App\Actions\Tracks\TrackPopulateAction;
use App\Models\AuthorRole;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFileType;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\PresentationSetting;
use App\Panel\ScheduledConference\Livewire\ReviewSetupSetting;
use App\Panel\ScheduledConference\Livewire\SubmissionFileTypeTable;
use App\Panel\ScheduledConference\Livewire\SubmissionFormItemTable;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\UploadFilesStep;
use App\Panel\ScheduledConference\Pages\Payments;
use App\Panel\ScheduledConference\Pages\Presentations;
use App\Panel\ScheduledConference\Pages\ReviewResult;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Panel\ScheduledConference\Widgets\Overview;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class BuiltinTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_builtin_labels_follow_locale_without_mutating_names_or_identifiers(): void
    {
        [$conference, $scheduled] = $this->context();
        FilesTypePopulateAction::run($scheduled);
        TrackPopulateAction::run($scheduled);
        AuthorRolePopulateDefaultDataAction::run($conference);
        $file = SubmissionFileType::where('builtin_code', 'other')->firstOrFail();
        $track = Track::where('builtin_code', 'general_track')->firstOrFail();
        $role = AuthorRole::where('builtin_code', 'translator')->firstOrFail();
        $originals = [$file->getAttributes(), $track->getAttributes(), $role->getAttributes()];

        app()->setLocale('fr');
        $this->assertSame('Autre', $file->display_name);
        $this->assertSame('Thématique générale', $track->display_name);
        $this->assertSame('Traducteur', $role->display_name);
        app()->setLocale('en');
        $this->assertSame('Other', $file->display_name);
        $this->assertSame('General Track', $track->display_name);
        $this->assertSame('Translator', $role->display_name);
        $this->assertSame($originals, [$file->getAttributes(), $track->getAttributes(), $role->getAttributes()]);
    }

    public function test_custom_legacy_unknown_and_renamed_names_are_preserved(): void
    {
        app()->setLocale('fr');
        foreach ([SubmissionFileType::class, AuthorRole::class, Track::class] as $model) {
            $field = $model === Track::class ? 'title' : 'name';
            $names = $model::builtinNames();
            $code = array_key_first($names);
            $record = (new $model)->forceFill([$field => $names[$code]]);
            $this->assertSame($names[$code], $record->display_name, 'Uncoded legacy names are not inferred.');
            $record->builtin_code = 'unknown';
            $this->assertSame($names[$code], $record->display_name);
            $record->forceFill(['builtin_code' => $code, $field => 'Organizer’s custom name']);
            $this->assertSame('Organizer’s custom name', $record->display_name);
        }
    }

    public function test_all_staging_file_codes_are_supported_but_not_populated_as_official_defaults(): void
    {
        [, $scheduled] = $this->context();
        FilesTypePopulateAction::run($scheduled);
        $this->assertSame(['abstract', 'full_paper', 'poster', 'other'], SubmissionFileType::ordered()->pluck('builtin_code')->all());
        app()->setLocale('fr');
        foreach (SubmissionFileType::builtinNames() as $code => $name) {
            $record = (new SubmissionFileType)->forceFill(['name' => $name, 'builtin_code' => $code]);
            $this->assertSame(__('general.'.$code), $record->display_name);
            $this->assertNotSame('general.'.$code, $record->display_name);
        }
    }

    public function test_repopulating_does_not_duplicate_renamed_builtins_or_reset_citation_roles(): void
    {
        [$conference, $scheduled] = $this->context();
        FilesTypePopulateAction::run($scheduled);
        TrackPopulateAction::run($scheduled);
        AuthorRolePopulateDefaultDataAction::run($conference);
        SubmissionFileType::firstOrFail()->update(['name' => 'Custom upload']);
        Track::firstOrFail()->update(['title' => 'Custom track']);
        AuthorRole::firstOrFail()->update(['name' => 'Custom contributor']);
        $conference->setMeta('citation_contributor_authors', []);
        FilesTypePopulateAction::run($scheduled);
        TrackPopulateAction::run($scheduled);
        AuthorRolePopulateDefaultDataAction::run($conference);
        $this->assertSame(4, SubmissionFileType::count());
        $this->assertSame(1, Track::count());
        $this->assertSame(2, AuthorRole::count());
        $this->assertSame([], $conference->getMeta('citation_contributor_authors'));
        $this->assertSame('Custom upload', SubmissionFileType::firstOrFail()->display_name);
    }

    public function test_legacy_mapping_is_explicit_scoped_idempotent_and_supports_dry_run(): void
    {
        [$conference, $scheduled] = $this->context();
        $file = SubmissionFileType::create(['name' => 'Research Instrument', 'scheduled_conference_id' => $scheduled->id]);
        $track = Track::create(['title' => 'General Track', 'abbreviation' => 'general', 'scheduled_conference_id' => $scheduled->id]);
        $role = AuthorRole::create(['name' => 'Translator', 'conference_id' => $conference->id]);
        $options = [
            '--conference' => $conference->id,
            '--scheduled-conference' => $scheduled->id,
            '--file-type' => [$file->id.':research_instrument'],
            '--track' => [$track->id.':general_track'],
            '--author-role' => [$role->id.':translator'],
        ];
        $this->artisan('translations:map-builtins', [...$options, '--dry-run' => true])->assertSuccessful();
        $this->assertNull($file->fresh()->builtin_code);
        $this->artisan('translations:map-builtins', $options)->assertSuccessful();
        $this->artisan('translations:map-builtins', $options)->assertSuccessful();
        app()->setLocale('fr');
        $this->assertSame('Instrument de recherche', $file->fresh()->display_name);
        $this->assertSame('Thématique générale', $track->fresh()->display_name);
        $this->assertSame('Traducteur', $role->fresh()->display_name);
        $this->assertSame('Research Instrument', $file->fresh()->name);
        $this->assertSame('general', $track->fresh()->abbreviation);
    }

    public function test_invalid_or_cross_conference_mapping_does_not_partially_update_data(): void
    {
        [, $scheduled] = $this->context();
        $file = SubmissionFileType::create(['name' => 'Other', 'scheduled_conference_id' => $scheduled->id]);
        $custom = SubmissionFileType::create(['name' => 'Custom material', 'scheduled_conference_id' => $scheduled->id]);
        foreach ([
            ['--file-type' => [$file->id.':other']],
            ['--scheduled-conference' => $scheduled->id + 1, '--file-type' => [$file->id.':other']],
            ['--scheduled-conference' => $scheduled->id, '--file-type' => [$file->id.':other', $custom->id.':poster']],
        ] as $options) {
            $this->artisan('translations:map-builtins', $options)->assertFailed();
            $this->assertNull($file->fresh()->builtin_code);
            $this->assertNull($custom->fresh()->builtin_code);
        }
    }

    public function test_localized_user_roles_keep_values_permissions_and_assignments(): void
    {
        [$conference, $scheduled] = $this->context();
        $role = Role::withoutGlobalScopes()->create([
            'name' => UserRole::TrackEditor->value, 'guard_name' => 'web',
            'conference_id' => $conference->id, 'scheduled_conference_id' => $scheduled->id,
        ]);
        $user = User::create(['given_name' => 'Tester', 'email' => 'labels@example.test', 'password' => 'password123456']);
        $user->assignRole(UserRole::TrackEditor);
        app()->setLocale('fr');
        $this->assertSame('Éditeur de thématique', $role->display_name);
        $this->assertSame('Track Editor', UserRole::TrackEditor->value);
        $this->assertSame('Éditeur de thématique', UserRole::options()['Track Editor']);
        $this->assertTrue($user->hasRole(UserRole::TrackEditor));
        $this->assertDatabaseHas('model_has_roles', ['role_id' => $role->id, 'model_id' => $user->id]);
        $this->assertSame('Organizer custom role', (new Role(['name' => 'Organizer custom role']))->display_name);
    }

    public function test_required_upload_statuses_translate_labels_while_preserving_file_type_ids(): void
    {
        [$conference, $scheduled] = $this->context();
        FilesTypePopulateAction::run($scheduled);
        $file = SubmissionFileType::where('builtin_code', 'other')->firstOrFail();
        $file->update(['required' => true]);
        $user = User::create(['given_name' => 'Uploader', 'email' => 'uploader@example.test', 'password' => 'password123456']);
        TrackPopulateAction::run($scheduled);
        $submission = Submission::withoutEvents(fn () => Submission::forceCreate([
            'conference_id' => $conference->id, 'scheduled_conference_id' => $scheduled->id,
            'user_id' => $user->id, 'track_id' => Track::firstOrFail()->id,
        ]));
        $step = new UploadFilesStep;
        $step->record = $submission;
        app()->setLocale('fr');
        $this->assertSame([['id' => $file->id, 'name' => 'Autre', 'uploaded' => false]], $step->requiredUploadTypeStatuses()->all());
        $this->assertSame(['Autre'], $step->missingRequiredUploadTypeNames()->all());
    }

    public function test_scoped_workflow_labels_and_navigation_follow_english_and_french(): void
    {
        $this->context();
        foreach (['en', 'fr'] as $locale) {
            app()->setLocale($locale);
            Livewire::test(PresentationSetting::class)
                ->assertSee(__('general.presentation_settings'))
                ->assertSee(__('general.presentation_open_date'))
                ->assertSee(__('general.presentation_close_date'));
            Livewire::test(ReviewSetupSetting::class)->assertSee(__('general.default_open_review_for_author'));
            Livewire::test(SubmissionFormItemTable::class)->assertSee(__('general.submission_form'))->assertSee(__('general.new_item'));
            $this->assertSame(__('general.presentations'), Presentations::getNavigationLabel());
            $this->assertSame(__('general.presentations'), (new Presentations)->getTitle());
            $this->assertSame(__('general.review_result'), ReviewResult::getNavigationLabel());
            $this->assertSame(__('general.review_result'), (new ReviewResult)->getTitle());
            $this->assertSame(__('general.make_a_submission'), (new CreateSubmission)->getTitle());
        }
    }

    public function test_missing_french_translation_falls_back_to_english_and_catalog_edits_are_used(): void
    {
        app()->setLocale('fr');
        app('translator')->addLines(['general.other' => 'Choix modifié'], 'fr');
        $file = (new SubmissionFileType)->forceFill(['name' => 'Other', 'builtin_code' => 'other']);
        $this->assertSame('Choix modifié', $file->display_name);
        app('translator')->addLines(['general.fallback_test' => 'English fallback'], 'en');
        $this->assertSame('English fallback', __('general.fallback_test'));
    }

    public function test_payment_and_dashboard_labels_translate_while_payment_tab_ids_stay_stable(): void
    {
        [$conference, $scheduled] = $this->context();
        $user = User::create(['given_name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'password123456']);
        $this->actingAs($user);
        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('scheduledConference'));
        URL::defaults(['conference' => $conference->path, 'serie' => $scheduled->path]);
        $tabIds = null;
        foreach (['en', 'fr'] as $locale) {
            app()->setLocale($locale);
            $page = new Payments;
            $tabs = collect($page->infolist(Infolist::make($page))->getFlatComponents())
                ->filter(fn ($component) => $component instanceof Tab);
            $labels = $tabs->map(fn ($tab) => $tab->getLabel())->all();
            foreach (['general', 'submission_fees', 'participant_fees', 'invoice', 'payment_method'] as $key) {
                $this->assertContains(__('general.'.$key), $labels);
            }
            $ids = $tabs->map(fn ($tab) => $tab->getId())->values()->all();
            if ($tabIds !== null) {
                $this->assertSame($tabIds, $ids);
            }
            $tabIds = $ids;
            $widget = new Overview;
            $components = collect($widget->scheduledConferenceInfolist(\App\Infolists\Infolist::make($widget))->getFlatComponents(true));
            $entries = $components->filter(fn ($component) => $component instanceof TextEntry)
                ->mapWithKeys(fn ($component) => [$component->getName() => $component]);
            $this->assertSame(__('general.submitted'), $entries['submitted']->getLabel());
            $this->assertSame(__('general.full_date'), $entries['full_date']->getLabel());
            $this->assertSame(__('general.submissions_under_review'), $entries['reviews']->getLabel());
        }
    }

    public function test_file_type_table_renders_french_display_names_without_changing_edit_form_text(): void
    {
        [, $scheduled] = $this->context();
        FilesTypePopulateAction::run($scheduled);
        $file = SubmissionFileType::where('builtin_code', 'other')->firstOrFail();
        app()->setLocale('fr');
        Livewire::test(SubmissionFileTypeTable::class)
            ->assertSee('Autre')
            ->mountTableAction('edit', $file)
            ->assertTableActionDataSet(['name' => 'Other']);
    }

    private function context(): array
    {
        $conference = Conference::withoutEvents(fn () => Conference::create(['name' => 'Conference', 'path' => 'conference']));
        $scheduled = ScheduledConference::withoutEvents(fn () => ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->id, 'title' => 'Scheduled', 'path' => 'scheduled',
        ]));
        app()->setCurrentConferenceId($conference->id);
        app()->setCurrentScheduledConferenceId($scheduled->id);

        return [$conference, $scheduled];
    }

    public function test_submission_creation_options_translate_with_unchanged_track_and_role_ids(): void
    {
        [$conference, $scheduled] = $this->context();
        TrackPopulateAction::run($scheduled);
        $track = Track::firstOrFail();
        $role = Role::withoutGlobalScopes()->create([
            'name' => UserRole::Author->value, 'guard_name' => 'web',
            'conference_id' => $conference->id, 'scheduled_conference_id' => $scheduled->id,
        ]);
        Permission::findOrCreate('Submission:submitAs', 'web');
        $user = User::create(['given_name' => 'Author', 'email' => 'create-options@example.test', 'password' => 'password123456']);
        $user->assignRole(UserRole::Author);
        $this->actingAs($user);
        app()->setLocale('fr');
        $page = new CreateSubmission;
        $components = collect($page->form(Form::make($page))->getFlatComponents(true))
            ->filter(fn ($component) => $component instanceof \Filament\Forms\Components\Radio)
            ->mapWithKeys(fn ($component) => [$component->getName() => $component]);
        $this->assertSame([$track->id => 'Thématique générale'], $components['track_id']->getOptions());
        $this->assertSame([$role->id => __('general.author')], $components['user_group_id']->getOptions());
    }

    public function test_renamed_coded_author_role_still_serves_as_the_default_contributor_role(): void
    {
        [$conference, $scheduled] = $this->context();
        AuthorRolePopulateDefaultDataAction::run($conference);
        $role = AuthorRole::where('builtin_code', 'author')->firstOrFail();
        $role->update(['name' => 'Main researcher']);
        AuthorRole::create(['conference_id' => $conference->id, 'name' => 'Author']);
        $component = new class extends ContributorList
        {
            public function defaultRole(): ?AuthorRole
            {
                return $this->getDefaultAuthorRole();
            }
        };
        $component->submission = (new Submission)->forceFill(['conference_id' => $conference->id, 'scheduled_conference_id' => $scheduled->id]);
        $this->assertSame($role->id, $component->defaultRole()->id);
        $this->assertSame('Main researcher', $component->defaultRole()->display_name);
    }
}
