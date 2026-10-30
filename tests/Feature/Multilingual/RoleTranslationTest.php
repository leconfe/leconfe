<?php

namespace Tests\Feature\Multilingual;

use App\Models\Author;
use App\Models\AuthorRole;
use App\Models\Committee;
use App\Models\CommitteeRole;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\ScheduledConference;
use App\Models\Speaker;
use App\Models\SpeakerRole;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\AuthorRoleTable;
use App\Panel\ScheduledConference\Livewire\CommitteeRoleTable;
use App\Panel\ScheduledConference\Livewire\SpeakerRoleTable;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Resources\CommitteeResource\Pages\ManageCommittee;
use App\Panel\ScheduledConference\Resources\SpeakerResource\Pages\ManageSpeakers;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class RoleTranslationTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        Gate::before(fn () => true);

        $this->user = $this->makeAuthor('role-manager@example.test');
        $this->actingAs($this->user);
    }

    public function test_speaker_role_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(SpeakerRoleTable::class)
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.name.en')
            ->assertSeeHtml('mountedTableActionsData.0.name.id')
            ->setTableActionData(['name' => ['en' => '', 'id' => 'Pembicara Tamu']])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['name.en' => 'required'])
            ->assertHasNoTableActionErrors(['name.id'])
            ->setTableActionData(['name' => ['en' => 'Guest Speaker', 'id' => 'Pembicara Tamu']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $role = SpeakerRole::query()->where('name', 'Guest Speaker')->firstOrFail();

        $this->assertSame($this->scheduledConference->getKey(), $role->scheduled_conference_id);
        $this->assertSame('Pembicara Tamu', $role->translated('name', 'id'));

        Livewire::test(SpeakerRoleTable::class)
            ->mountTableAction('edit', $role)
            ->assertTableActionDataSet(['name' => ['en' => 'Guest Speaker', 'id' => 'Pembicara Tamu']])
            ->setTableActionData(['name' => ['en' => 'Invited Speaker', 'id' => 'Pembicara Undangan']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $role = SpeakerRole::query()->find($role->getKey());

        $this->assertSame('Invited Speaker', $role->name);
        $this->assertSame('Pembicara Undangan', $role->translated('name', 'id'));
    }

    public function test_committee_role_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(CommitteeRoleTable::class)
            ->callTableAction('create', data: ['name' => ['en' => 'Reviewer', 'id' => 'Penelaah']])
            ->assertHasNoTableActionErrors();

        $role = CommitteeRole::query()->where('name', 'Reviewer')->firstOrFail();

        $this->assertSame('Penelaah', $role->translated('name', 'id'));

        Livewire::test(CommitteeRoleTable::class)
            ->mountTableAction('edit', $role)
            ->assertTableActionDataSet(['name' => ['en' => 'Reviewer', 'id' => 'Penelaah']])
            ->setTableActionData(['name' => ['en' => 'Scientific Reviewer', 'id' => 'Penelaah Ilmiah']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $role = CommitteeRole::query()->find($role->getKey());

        $this->assertSame('Scientific Reviewer', $role->name);
        $this->assertSame('Penelaah Ilmiah', $role->translated('name', 'id'));
    }

    public function test_author_role_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(AuthorRoleTable::class)
            ->callTableAction('create', data: ['name' => ['en' => 'Editor', 'id' => 'Penyunting']])
            ->assertHasNoTableActionErrors();

        $role = AuthorRole::query()->where('name', 'Editor')->firstOrFail();

        $this->assertSame($this->conference->getKey(), $role->conference_id);
        $this->assertSame('Penyunting', $role->translated('name', 'id'));

        Livewire::test(AuthorRoleTable::class)
            ->mountTableAction('edit', $role)
            ->assertTableActionDataSet(['name' => ['en' => 'Editor', 'id' => 'Penyunting']])
            ->setTableActionData(['name' => ['en' => 'Volume Editor', 'id' => 'Penyunting Jilid']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $role = AuthorRole::query()->find($role->getKey());

        $this->assertSame('Volume Editor', $role->name);
        $this->assertSame('Penyunting Jilid', $role->translated('name', 'id'));
    }

    public function test_only_the_default_language_name_has_to_be_unique(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(CommitteeRoleTable::class)
            ->callTableAction('create', data: ['name' => ['en' => 'Chair', 'id' => 'Ketua']])
            ->assertHasTableActionErrors(['name.en' => 'unique']);

        Livewire::test(CommitteeRoleTable::class)
            ->callTableAction('create', data: ['name' => ['en' => 'Organiser', 'id' => 'Chair']])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Chair', CommitteeRole::query()->where('name', 'Organiser')->firstOrFail()->translated('name', 'id'));

        $chair = CommitteeRole::query()->where('name', 'Chair')->firstOrFail();

        Livewire::test(CommitteeRoleTable::class)
            ->callTableAction('edit', $chair, data: ['name' => ['en' => 'Chair', 'id' => 'Ketua']])
            ->assertHasNoTableActionErrors();
    }

    public function test_role_forms_keep_single_language_behaviour(): void
    {
        Livewire::test(SpeakerRoleTable::class)
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.name"')
            ->assertDontSeeHtml('mountedTableActionsData.0.name.en')
            ->setTableActionData(['name' => 'Guest Speaker'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $role = SpeakerRole::query()->where('name', 'Guest Speaker')->firstOrFail();
        $this->assertNoTranslationRows($role);

        Livewire::test(SpeakerRoleTable::class)
            ->callTableAction('create', data: ['name' => 'Keynote Speaker'])
            ->assertHasTableActionErrors(['name' => 'unique']);

        Livewire::test(SpeakerRoleTable::class)
            ->callTableAction('edit', $role, data: ['name' => 'Invited Speaker'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Invited Speaker', $role->refresh()->name);
        $this->assertNoTranslationRows($role);
    }

    public function test_role_tables_show_the_ui_language(): void
    {
        $this->enableLanguages(['en', 'id', 'fr']);

        SpeakerRole::query()->where('name', 'Keynote Speaker')->firstOrFail()->setTranslation('name', 'id', 'Pembicara Utama');
        CommitteeRole::query()->where('name', 'Chair')->firstOrFail()->setTranslation('name', 'id', 'Ketua');
        AuthorRole::query()->where('name', 'Translator')->firstOrFail()->setTranslation('name', 'id', 'Penerjemah');

        app()->setLocale('id');

        Livewire::test(SpeakerRoleTable::class)->assertSee('Pembicara Utama')->assertDontSee('Keynote Speaker')->assertSee('Plenary Speaker');
        Livewire::test(CommitteeRoleTable::class)->assertSee('Ketua')->assertSee('Member');
        Livewire::test(AuthorRoleTable::class)->assertSee('Penerjemah')->assertDontSee('Translator');

        app()->setLocale('fr');

        Livewire::test(SpeakerRoleTable::class)->assertSee('Keynote Speaker')->assertDontSee('Pembicara Utama');
    }

    public function test_speaker_and_committee_lists_and_role_choices_show_the_ui_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $keynote = SpeakerRole::query()->where('name', 'Keynote Speaker')->firstOrFail();
        $keynote->setTranslation('name', 'id', 'Pembicara Utama');
        $chair = CommitteeRole::query()->where('name', 'Chair')->firstOrFail();
        $chair->setTranslation('name', 'id', 'Ketua Panitia');

        $speaker = Speaker::query()->create(['given_name' => 'Rina', 'family_name' => 'Wati', 'email' => 'rina@example.test', 'speaker_role_id' => $keynote->getKey()]);
        Committee::query()->create(['given_name' => 'Budi', 'family_name' => 'Santoso', 'email' => 'budi@example.test', 'committee_role_id' => $chair->getKey()]);

        app()->setLocale('id');

        Livewire::test(ManageSpeakers::class)
            ->assertSee('Pembicara Utama')
            ->assertDontSee('Keynote Speaker')
            ->mountTableAction('edit', $speaker)
            ->assertSee('Pembicara Utama')
            ->assertSee('Plenary Speaker')
            ->assertDontSee('Keynote Speaker');

        Livewire::test(ManageCommittee::class)
            ->assertSee('Ketua Panitia')
            ->assertDontSee('>Chair<', false);
    }

    public function test_role_created_from_the_speaker_form_keeps_its_translations(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ManageSpeakers::class)
            ->mountTableAction('create')
            ->mountFormComponentAction('speaker_role_id', 'createOption', formName: 'mountedTableActionForm')
            ->setFormComponentActionData(['name' => ['en' => 'Panelist', 'id' => 'Panelis']])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();

        $role = SpeakerRole::query()->where('name', 'Panelist')->firstOrFail();

        $this->assertSame('Panelis', $role->translated('name', 'id'));
    }

    public function test_contributor_list_shows_author_roles_in_the_ui_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $translator = AuthorRole::query()->where('name', 'Translator')->firstOrFail();
        $translator->setTranslation('name', 'id', 'Penerjemah');

        $submission = $this->makePaper($this->user, 'en', ['title' => ['en' => 'Coral reefs']]);
        $this->makeContributor($submission, $translator);

        app()->setLocale('id');

        Livewire::test(ContributorList::class, ['submission' => $submission])
            ->assertSee('Penerjemah')
            ->assertDontSee('Translator')
            ->mountTableAction('create')
            ->assertSee('Penerjemah');
    }

    public function test_frontend_shows_role_names_in_the_session_language(): void
    {
        $this->enableLanguages(['en', 'id', 'fr']);

        $keynote = SpeakerRole::query()->where('name', 'Keynote Speaker')->firstOrFail();
        $keynote->setTranslation('name', 'id', 'Pembicara Utama');
        Speaker::query()->create(['given_name' => 'Rina', 'family_name' => 'Wati', 'email' => 'rina@example.test', 'speaker_role_id' => $keynote->getKey()]);

        $chair = CommitteeRole::query()->where('name', 'Chair')->firstOrFail();
        $chair->setTranslation('name', 'id', 'Ketua Panitia');
        Committee::query()->create(['given_name' => 'Budi', 'family_name' => 'Santoso', 'email' => 'budi@example.test', 'committee_role_id' => $chair->getKey()]);

        $parameters = ['conference' => $this->conference->path, 'serie' => $this->scheduledConference->path];

        $this->frontend('id', route('livewirePageGroup.scheduledConference.pages.home', $parameters))
            ->assertSee('Pembicara Utama')
            ->assertDontSee('Keynote Speaker');

        $this->frontend('fr', route('livewirePageGroup.scheduledConference.pages.home', $parameters))
            ->assertSee('Keynote Speaker')
            ->assertDontSee('Pembicara Utama');

        $this->frontend('id', route('livewirePageGroup.scheduledConference.pages.committees', $parameters))
            ->assertSee('Ketua Panitia')
            ->assertDontSee('>Chair<', false);
    }

    public function test_paper_page_shows_author_roles_in_the_session_language(): void
    {
        $this->enableLanguages(['en', 'id', 'fr']);

        $translator = AuthorRole::query()->where('name', 'Translator')->firstOrFail();
        $translator->setTranslation('name', 'id', 'Penerjemah');

        $paper = $this->makePaper($this->user, 'en', ['title' => ['en' => 'Coral reefs']], SubmissionStage::Proceeding, SubmissionStatus::Published, $this->makeProceeding());
        $this->makeContributor($paper, $translator);

        $this->visitPaper($this->conference, $paper, 'id')
            ->assertSee('Penerjemah')
            ->assertDontSee('Translator');

        $this->visitPaper($this->conference, $paper, 'fr')
            ->assertSee('Traducteur')
            ->assertDontSee('Penerjemah');
    }

    public function test_role_translation_queries_do_not_grow_with_the_number_of_roles(): void
    {
        $this->enableLanguages(['en', 'id']);

        $url = route('livewirePageGroup.scheduledConference.pages.committees', ['conference' => $this->conference->path, 'serie' => $this->scheduledConference->path]);

        $this->makeCommitteeRoles(2);
        $few = $this->countTranslateQueries(CommitteeRole::class, fn () => $this->frontend('id', $url)->assertSee('Peran 2'));
        $fewPanel = $this->countTranslateQueries(CommitteeRole::class, fn () => Livewire::test(CommitteeRoleTable::class)->assertSee('Peran 2'));

        $this->makeCommitteeRoles(4, offset: 2);
        $many = $this->countTranslateQueries(CommitteeRole::class, fn () => $this->frontend('id', $url)->assertSee('Peran 6'));
        $manyPanel = $this->countTranslateQueries(CommitteeRole::class, fn () => Livewire::test(CommitteeRoleTable::class)->assertSee('Peran 6'));

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many);
        $this->assertSame($fewPanel, $manyPanel);
    }

    protected function makeCommitteeRoles(int $count, int $offset = 0): void
    {
        foreach (range($offset + 1, $offset + $count) as $number) {
            $role = CommitteeRole::query()->create(['name' => "Role {$number}"]);
            $role->setTranslation('name', 'id', "Peran {$number}");

            Committee::query()->create(['given_name' => "Member {$number}", 'email' => "member{$number}@example.test", 'committee_role_id' => $role->getKey()]);
        }
    }

    protected function countTranslateQueries(string $model, callable $callback): int
    {
        $count = 0;
        $morphClass = (new $model)->getMorphClass();

        DB::listen(function (QueryExecuted $query) use (&$count, $morphClass) {
            if (preg_match('/\bfrom\s+["`]?translate["`]?/i', $query->sql) && in_array($morphClass, $query->bindings, true)) {
                $count++;
            }
        });

        $callback();

        app('events')->forget(QueryExecuted::class);

        return $count;
    }

    protected function makeContributor(Model $submission, AuthorRole $role): Author
    {
        return Author::query()->forceCreate([
            'submission_id' => $submission->getKey(),
            'author_role_id' => $role->getKey(),
            'given_name' => 'Dewi',
            'family_name' => 'Sartika',
            'email' => 'dewi@example.test',
        ]);
    }

    protected function frontend(string $locale, string $url)
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get($url)
            ->assertOk();
    }

    protected function assertNoTranslationRows(Model $model): void
    {
        $this->assertFalse(Translate::query()
            ->where('translatable_type', $model->getMorphClass())
            ->where('translatable_id', $model->getKey())
            ->exists());
    }
}
