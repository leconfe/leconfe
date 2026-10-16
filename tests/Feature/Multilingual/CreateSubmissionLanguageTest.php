<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Permissions\PermissionPopulateAction;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Timeline;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\DetailStep;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class CreateSubmissionLanguageTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        // Permissions are covered elsewhere; these tests cover the paper language.
        Gate::before(fn () => true);
        PermissionPopulateAction::run();

        Timeline::query()->create([
            'name' => 'Submission open',
            'date' => now()->subDay(),
            'type' => Timeline::TYPE_SUBMISSION_OPEN,
        ]);

        $this->makeTrack();
        $this->author = $this->makeAuthor();
        $this->actingAs($this->author);
    }

    public function test_the_chosen_paper_language_title_is_required_and_comes_first(): void
    {
        $this->enableLanguages(['en', 'id']);

        $this->createForm()
            ->assertSee(__('general.paper_language'))
            ->assertSet('data.meta.language', 'en')
            ->assertSeeHtmlInOrder(['data.meta.title.en', 'data.meta.title.id'])
            ->set('data.meta.language', 'id')
            ->assertSeeHtmlInOrder(['data.meta.title.id', 'data.meta.title.en'])
            ->set('data.meta.title', ['id' => '', 'en' => 'Coral reefs'])
            ->call('submit')
            ->assertHasErrors(['data.meta.title.id' => 'required'])
            ->assertHasNoErrors(['data.meta.title.en']);

        $this->assertSame(0, Submission::query()->count());
    }

    public function test_submitting_stores_the_paper_language_and_every_title(): void
    {
        $this->enableLanguages(['en', 'id']);

        $this->createForm()
            ->set('data.meta.language', 'id')
            ->set('data.meta.title', ['id' => 'Terumbu karang', 'en' => 'Coral reefs'])
            ->call('submit')
            ->assertHasNoErrors();

        $submission = Submission::query()->sole();

        $this->assertSame('id', $submission->getMeta('language'));
        $this->assertSame('Terumbu karang', $submission->getMeta('title'));
        $this->assertSame('Terumbu karang', $submission->getStoredTranslation('title', 'id'));
        $this->assertSame('Coral reefs', $submission->getStoredTranslation('title', 'en'));

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->assertSet('meta.language', 'id')
            ->assertSet('meta.title.id', 'Terumbu karang')
            ->assertSet('meta.title.en', 'Coral reefs')
            ->assertSeeHtmlInOrder(['meta.title.id', 'meta.title.en']);
    }

    public function test_the_title_in_another_language_is_optional(): void
    {
        $this->enableLanguages(['en', 'id']);

        $this->createForm()
            ->set('data.meta.language', 'id')
            ->set('data.meta.title', ['id' => 'Terumbu karang', 'en' => ''])
            ->call('submit')
            ->assertHasNoErrors();

        $submission = Submission::query()->sole();

        $this->assertSame('id', $submission->getMeta('language'));
        $this->assertSame('Terumbu karang', $submission->getMeta('title'));
        $this->assertNull($submission->getStoredTranslation('title', 'en'));
        $this->assertSame('Terumbu karang', $submission->translated('title', 'en'));
    }

    public function test_changing_the_language_moves_the_typed_title(): void
    {
        $this->enableLanguages(['en', 'id']);

        $this->createForm()
            ->set('data.meta.title.en', 'Terumbu karang')
            ->set('data.meta.language', 'id')
            ->assertSet('data.meta.title.id', 'Terumbu karang')
            ->assertSet('data.meta.title.en', null);
    }

    public function test_the_form_is_unchanged_with_one_language(): void
    {
        $this->createForm()
            ->assertDontSee(__('general.paper_language'))
            ->assertDontSeeHtml('data.meta.title.en')
            ->set('data.meta.title', 'Coral reefs')
            ->call('submit')
            ->assertHasNoErrors();

        $submission = Submission::query()->sole();

        $this->assertSame('en', $submission->getMeta('language'));
        $this->assertSame('Coral reefs', $submission->getMeta('title'));
        $this->assertSame(0, $submission->translate()->count());

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->assertSet('meta.title', 'Coral reefs');
    }

    protected function createForm(): Testable
    {
        return Livewire::test(CreateSubmission::class)
            ->set('data.submissionRequirements', true)
            ->set('data.privacy_consent', true);
    }
}
