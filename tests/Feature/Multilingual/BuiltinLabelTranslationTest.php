<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Authors\AuthorRolePopulateDefaultDataAction;
use App\Actions\Conferences\ConferenceCloneAction;
use App\Actions\SubmissionFiles\FilesTypePopulateAction;
use App\Actions\Tracks\TrackPopulateAction;
use App\Models\AuthorRole;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFileType;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\SubmissionFileTypeTable;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Livewire\TrackTable;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\UploadFilesStep;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class BuiltinLabelTranslationTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Gate::before(fn () => true);

        $this->actingAs(User::query()->create([
            'given_name' => 'Content',
            'family_name' => 'Manager',
            'email' => 'builtin-labels@example.test',
            'password' => 'password123456',
        ]));
    }

    public function test_new_conference_defaults_follow_the_interface_language_without_changing_stored_names(): void
    {
        $file = SubmissionFileType::query()->where('builtin_code', 'other')->firstOrFail();
        $track = Track::query()->where('builtin_code', 'general_track')->firstOrFail();
        $role = AuthorRole::query()->where('builtin_code', 'translator')->firstOrFail();
        $stored = [$file->getAttributes(), $track->getAttributes(), $role->getAttributes()];

        app()->setLocale('fr');
        $this->assertSame('Autre', $file->translated('name'));
        $this->assertSame('Axe thématique général', $track->translated('title'));
        $this->assertSame('Traducteur', $role->translated('name'));

        app()->setLocale('en');
        $this->assertSame('Other', $file->translated('name'));
        $this->assertSame('General Track', $track->translated('title'));
        $this->assertSame('Translator', $role->translated('name'));

        $this->assertSame($stored, [$file->fresh()->getAttributes(), $track->fresh()->getAttributes(), $role->fresh()->getAttributes()]);
    }

    public function test_file_type_and_track_tables_show_french_defaults_while_edit_forms_keep_the_stored_name(): void
    {
        $file = SubmissionFileType::query()->where('builtin_code', 'other')->firstOrFail();
        app()->setLocale('fr');

        Livewire::test(SubmissionFileTypeTable::class)
            ->loadTable()
            ->assertSee('Article complet')
            ->assertSee('Autre')
            ->mountTableAction('edit', $file)
            ->assertTableActionDataSet(['name' => 'Other']);

        Livewire::test(TrackTable::class)
            ->loadTable()
            ->assertSee('Axe thématique général');
    }

    public function test_an_organizer_translation_wins_over_the_catalog(): void
    {
        $this->enableLanguages(['en', 'fr']);
        $file = SubmissionFileType::query()->where('builtin_code', 'poster')->firstOrFail();
        $file->setTranslation('name', 'fr', 'Affiche scientifique');

        $this->assertSame('Affiche scientifique', $file->translated('name', 'fr'));
        $this->assertSame('Poster', $file->translated('name', 'en'));
    }

    public function test_renamed_uncoded_and_unknown_records_keep_their_stored_names(): void
    {
        app()->setLocale('fr');

        foreach ([SubmissionFileType::class => 'name', AuthorRole::class => 'name', Track::class => 'title'] as $model => $column) {
            $names = $model::builtinNames();
            $code = array_key_first($names);

            $record = (new $model)->forceFill([$column => $names[$code]]);
            $this->assertSame($names[$code], $record->translated($column), "{$model} without a code is organizer text.");

            $record->forceFill(['builtin_code' => 'unknown']);
            $this->assertSame($names[$code], $record->translated($column));

            $record->forceFill(['builtin_code' => $code, $column => 'Organizer’s custom name']);
            $this->assertSame('Organizer’s custom name', $record->translated($column));
        }
    }

    public function test_every_supported_file_type_code_has_a_catalog_entry_but_only_four_are_created(): void
    {
        $this->assertSame(
            ['abstract', 'full_paper', 'poster', 'other'],
            SubmissionFileType::query()->ordered()->pluck('builtin_code')->all(),
        );

        $french = require lang_path('fr/general.php');

        app()->setLocale('fr');
        foreach (SubmissionFileType::builtinNames() as $code => $name) {
            $record = (new SubmissionFileType)->forceFill(['name' => $name, 'builtin_code' => $code]);

            $this->assertArrayHasKey($code, $french);
            $this->assertSame($french[$code], $record->translated('name'));
        }
    }

    public function test_repopulating_keeps_renamed_defaults_and_citation_roles(): void
    {
        SubmissionFileType::query()->where('builtin_code', 'abstract')->firstOrFail()->update(['name' => 'Custom upload']);
        Track::query()->where('builtin_code', 'general_track')->firstOrFail()->update(['title' => 'Custom track']);
        AuthorRole::query()->where('builtin_code', 'author')->firstOrFail()->update(['name' => 'Custom contributor']);
        $this->conference->setMeta('citation_contributor_authors', []);

        FilesTypePopulateAction::run($this->scheduledConference);
        TrackPopulateAction::run($this->scheduledConference);
        AuthorRolePopulateDefaultDataAction::run($this->conference);

        $this->assertSame(4, SubmissionFileType::query()->count());
        $this->assertSame(1, Track::query()->count());
        $this->assertSame(2, AuthorRole::query()->count());
        $this->assertSame([], $this->conference->getMeta('citation_contributor_authors'));
        app()->setLocale('fr');
        $this->assertSame('Custom upload', SubmissionFileType::query()->where('builtin_code', 'abstract')->firstOrFail()->translated('name'));
    }

    public function test_a_cloned_conference_cites_its_own_author_and_translator_roles(): void
    {
        app()->setCurrentConferenceId(0);

        $clone = ConferenceCloneAction::run([
            'conference_id' => $this->conference->getKey(),
            'name' => 'Cloned Conference',
            'path' => 'cloned',
        ]);

        $roles = fn (Conference $conference) => AuthorRole::withoutGlobalScopes()
            ->where('conference_id', $conference->getKey())
            ->pluck('id', 'builtin_code');
        $sourceRoles = $roles($this->conference);
        $cloneRoles = $roles($clone);

        $source = Conference::query()->findOrFail($this->conference->getKey());
        $clone = Conference::query()->findOrFail($clone->getKey());

        $this->assertSame([$cloneRoles['author']], $clone->getMeta('citation_contributor_authors'));
        $this->assertSame([$cloneRoles['translator']], $clone->getMeta('citation_contributor_translators'));
        $this->assertSame([$sourceRoles['author']], $source->getMeta('citation_contributor_authors'));
        $this->assertSame([$sourceRoles['translator']], $source->getMeta('citation_contributor_translators'));
    }

    public function test_legacy_records_are_mapped_only_by_an_explicit_reviewed_command(): void
    {
        $file = SubmissionFileType::query()->create(['name' => 'Research Instrument', 'scheduled_conference_id' => $this->scheduledConference->getKey()]);
        $track = Track::query()->where('builtin_code', 'general_track')->firstOrFail();
        $track->forceFill(['builtin_code' => null])->save();
        $role = AuthorRole::query()->where('builtin_code', 'translator')->firstOrFail();
        $role->forceFill(['builtin_code' => null])->save();

        $options = [
            '--conference' => $this->conference->getKey(),
            '--scheduled-conference' => $this->scheduledConference->getKey(),
            '--file-type' => [$file->getKey().':research_instrument'],
            '--track' => [$track->getKey().':general_track'],
            '--author-role' => [$role->getKey().':translator'],
        ];

        $this->artisan('translations:map-builtins', [...$options, '--dry-run' => true])->assertSuccessful();
        $this->assertNull($file->fresh()->builtin_code);

        $this->artisan('translations:map-builtins', $options)->assertSuccessful();
        $this->artisan('translations:map-builtins', $options)->assertSuccessful();

        app()->setLocale('fr');
        $this->assertSame('Instrument de recherche', $file->fresh()->translated('name'));
        $this->assertSame('Axe thématique général', $track->fresh()->translated('title'));
        $this->assertSame('Traducteur', $role->fresh()->translated('name'));
        $this->assertSame('Research Instrument', $file->fresh()->name);
        $this->assertSame('general', $track->fresh()->abbreviation);
    }

    public function test_an_invalid_mapping_changes_nothing(): void
    {
        $file = SubmissionFileType::query()->create(['name' => 'Data Set', 'scheduled_conference_id' => $this->scheduledConference->getKey()]);
        $custom = SubmissionFileType::query()->create(['name' => 'Custom material', 'scheduled_conference_id' => $this->scheduledConference->getKey()]);

        foreach ([
            ['--file-type' => [$file->getKey().':data_set']],
            ['--scheduled-conference' => $this->scheduledConference->getKey() + 1, '--file-type' => [$file->getKey().':data_set']],
            ['--scheduled-conference' => $this->scheduledConference->getKey(), '--file-type' => [$file->getKey().':data_set', $custom->getKey().':source_text']],
        ] as $options) {
            $this->artisan('translations:map-builtins', $options)->assertFailed();
            $this->assertNull($file->fresh()->builtin_code);
            $this->assertNull($custom->fresh()->builtin_code);
        }
    }

    public function test_required_upload_types_are_listed_in_the_interface_language(): void
    {
        $file = SubmissionFileType::query()->where('builtin_code', 'other')->firstOrFail();
        $file->update(['required' => true]);

        $submission = Submission::withoutEvents(fn () => Submission::query()->forceCreate([
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'user_id' => auth()->id(),
            'track_id' => Track::query()->firstOrFail()->getKey(),
        ]));

        $step = new UploadFilesStep;
        $step->record = $submission;

        app()->setLocale('fr');
        $this->assertSame([['id' => $file->getKey(), 'name' => 'Autre', 'uploaded' => false]], $step->requiredUploadTypeStatuses()->all());
        $this->assertSame(['Autre'], $step->missingRequiredUploadTypeNames()->all());
    }

    public function test_a_renamed_default_author_role_stays_the_default_contributor_role(): void
    {
        $role = AuthorRole::query()->where('builtin_code', 'author')->firstOrFail();
        $role->update(['name' => 'Main researcher']);
        AuthorRole::query()->create(['conference_id' => $this->conference->getKey(), 'name' => 'Author']);

        $component = new class extends ContributorList
        {
            public function defaultRole(): ?AuthorRole
            {
                return $this->getDefaultAuthorRole();
            }
        };
        $component->submission = (new Submission)->forceFill([
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
        ]);

        $this->assertSame($role->getKey(), $component->defaultRole()->getKey());
    }

    public function test_catalog_edits_apply_without_component_changes(): void
    {
        app()->setLocale('fr');
        app('translator')->addLines(['general.other' => 'Choix modifié'], 'fr');

        $this->assertSame('Choix modifié', SubmissionFileType::query()->where('builtin_code', 'other')->firstOrFail()->translated('name'));
    }
}
