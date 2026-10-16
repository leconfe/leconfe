<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Permissions\PermissionPopulateAction;
use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\PaymentFee;
use App\Models\PaymentFeeFormItem;
use App\Models\PaymentFormItem;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFormItem;
use App\Models\Timeline;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\PaymentFeeFormItemTable;
use App\Panel\ScheduledConference\Livewire\PaymentFormItemTable;
use App\Panel\ScheduledConference\Livewire\SubmissionFormItemTable;
use App\Panel\ScheduledConference\Livewire\Submissions\Forms\AdditionalData;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class FormItemTranslationTest extends TestCase
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

        // Permissions are covered elsewhere; these tests cover the form item translations.
        Gate::before(fn () => true);
        PermissionPopulateAction::run();

        $this->makeTrack();
        $this->author = $this->makeAuthor();
        $this->actingAs($this->author);

        $this->enableLanguages(['en', 'id']);
    }

    public function test_the_submission_form_item_form_saves_every_language(): void
    {
        $options = [
            'en' => ['a' => ['text' => 'Oral'], 'b' => ['text' => 'Poster']],
            'id' => ['a' => ['text' => 'Lisan'], 'b' => ['text' => 'Poster']],
        ];

        Livewire::test(SubmissionFormItemTable::class)
            ->loadTable()
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.meta.name.id')
            ->assertSeeHtml('mountedTableActionsData.0.meta.description.id')
            ->set('mountedTableActionsData.0.type', SubmissionFormItem::TYPE_RADIO)
            ->assertSeeHtml('mountedTableActionsData.0.meta.response_options.id')
            ->set('mountedTableActionsData.0.meta.response_options', $options)
            ->set('mountedTableActionsData.0.meta.name', ['en' => '', 'id' => 'Presentasi'])
            ->set('mountedTableActionsData.0.meta.description', ['en' => 'How you present', 'id' => 'Cara presentasi'])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['meta.name.en' => 'required'])
            ->assertHasNoTableActionErrors(['meta.name.id'])
            ->set('mountedTableActionsData.0.meta.name', ['en' => 'Presentation', 'id' => 'Presentasi'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = SubmissionFormItem::query()->sole();

        $this->assertSame('Presentation', $item->getMeta('name'));
        $this->assertSame(['Oral', 'Poster'], $item->getMeta('response_options'));
        $this->assertSame('Presentasi', $item->translated('name', 'id'));
        $this->assertSame('Cara presentasi', $item->translated('description', 'id'));
        $this->assertSame(['Lisan', 'Poster'], $item->translatedResponseOptions('id'));

        Livewire::test(SubmissionFormItemTable::class)
            ->loadTable()
            ->mountTableAction('edit', $item)
            ->assertTableActionDataSet([
                'meta.name' => ['en' => 'Presentation', 'id' => 'Presentasi'],
                'meta.description' => ['en' => 'How you present', 'id' => 'Cara presentasi'],
            ])
            ->set('mountedTableActionsData.0.meta.name', ['en' => 'Presentation type', 'id' => 'Jenis presentasi'])
            ->set('mountedTableActionsData.0.meta.response_options', [
                'en' => ['a' => ['text' => 'Oral'], 'b' => ['text' => 'Poster']],
                'id' => ['a' => ['text' => 'Lisan'], 'b' => ['text' => 'Poster digital']],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = SubmissionFormItem::query()->sole();

        $this->assertSame('Presentation type', $item->getMeta('name'));
        $this->assertSame('Jenis presentasi', $item->translated('name', 'id'));
        $this->assertSame(['Lisan', 'Poster digital'], $item->translatedResponseOptions('id'));
    }

    public function test_the_payment_form_item_forms_save_every_language(): void
    {
        $component = Livewire::test(PaymentFormItemTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->loadTable()
            ->mountTableAction('create');

        // Only the primary language starts with an empty option to fill in.
        $this->assertCount(1, $component->get('mountedTableActionsData.0.meta.response_options.en'));
        $this->assertCount(0, $component->get('mountedTableActionsData.0.meta.response_options.id'));

        $component
            ->set('mountedTableActionsData.0.type', PaymentFormItem::TYPE_SELECT)
            ->set('mountedTableActionsData.0.meta', [
                'name' => ['en' => 'Diet', 'id' => 'Makanan'],
                'description' => ['en' => 'Your diet', 'id' => 'Pola makan Anda'],
                'response_options' => [
                    'en' => ['a' => ['text' => 'Vegetarian'], 'b' => ['text' => 'Any']],
                    'id' => ['a' => ['text' => 'Vegetarian'], 'b' => ['text' => 'Apa saja']],
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = PaymentFormItem::query()->sole();

        $this->assertSame('Diet', $item->getMeta('name'));
        $this->assertSame('Makanan', $item->translated('name', 'id'));
        $this->assertSame('Pola makan Anda', $item->translated('description', 'id'));
        $this->assertSame(['Vegetarian', 'Any'], $item->getMeta('response_options'));
        $this->assertSame(['Vegetarian', 'Apa saja'], $item->translatedResponseOptions('id'));

        $fee = PaymentFee::query()->create([
            'name' => 'Submission fee',
            'type' => PaymentManager::TYPE_SUBMISSION_FEE,
            'amount' => 100,
            'currency' => 'usd',
            'is_active' => true,
        ]);

        Livewire::test(PaymentFeeFormItemTable::class, ['record' => $fee])
            ->loadTable()
            ->callTableAction('create', data: [
                'type' => PaymentFeeFormItem::TYPE_CHECKBOX,
                'meta' => [
                    'name' => ['en' => 'Topics', 'id' => 'Topik'],
                    'description' => ['en' => 'Pick topics', 'id' => 'Pilih topik'],
                    'response_options' => ['en' => ['Ocean', 'Land'], 'id' => ['Laut', 'Darat']],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $item = PaymentFeeFormItem::query()->sole();

        $this->assertSame('Topics', $item->getMeta('name'));
        $this->assertSame('Topik', $item->translated('name', 'id'));
        $this->assertSame('Pilih topik', $item->translated('description', 'id'));
        $this->assertSame(['Ocean', 'Land'], $item->getMeta('response_options'));
        $this->assertSame(['Laut', 'Darat'], $item->translatedResponseOptions('id'));

        Livewire::test(PaymentFeeFormItemTable::class, ['record' => $fee])
            ->loadTable()
            ->mountTableAction('edit', $item)
            ->assertTableActionDataSet([
                'meta.name' => ['en' => 'Topics', 'id' => 'Topik'],
                'meta.response_options' => ['en' => ['Ocean', 'Land'], 'id' => ['Laut', 'Darat']],
            ]);
    }

    public function test_the_create_submission_form_shows_translated_items_and_stores_the_primary_option(): void
    {
        Timeline::query()->create([
            'name' => 'Submission open',
            'date' => now()->subDay(),
            'type' => Timeline::TYPE_SUBMISSION_OPEN,
        ]);

        $item = $this->makeSubmissionFormItem();

        app()->setLocale('id');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Presentasi')
            ->assertSee('Cara presentasi')
            ->assertSee('Lisan')
            ->assertSee('Poster digital')
            ->assertDontSee('How you present')
            ->assertSeeHtml('value="Oral"')
            ->set('data.meta.title', ['en' => 'Coral reefs', 'id' => ''])
            ->set("data.submission_form_responses.{$item->getKey()}", 'Oral')
            ->set('data.submissionRequirements', true)
            ->set('data.privacy_consent', true)
            ->call('submit')
            ->assertHasNoErrors();

        $submission = Submission::query()->sole();

        $this->assertSame([(string) $item->getKey() => 'Oral'], $submission->getMeta('submission_form_responses'));
    }

    public function test_existing_answers_are_still_shown(): void
    {
        $item = $this->makeSubmissionFormItem();

        $submission = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs'],
        ], SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        $submission->setMeta('submission_form_responses', [$item->getKey() => 'Poster']);

        app()->setLocale('id');

        Livewire::test(AdditionalData::class, ['submission' => $submission])
            ->assertSet("data.submission_form_responses.{$item->getKey()}", 'Poster')
            ->assertSee('Presentasi')
            ->assertSee('Poster digital')
            ->assertSeeHtml('value="Poster"');

        app()->setLocale('en');

        Livewire::test(AdditionalData::class, ['submission' => $submission])
            ->assertSet("data.submission_form_responses.{$item->getKey()}", 'Poster')
            ->assertSee('Presentation')
            ->assertDontSee('Poster digital');

        Livewire::test(ViewSubmission::class, ['record' => $submission->getKey()])
            ->assertOk()
            ->assertSee(__('general.additional_data'));
    }

    public function test_the_payment_detail_shows_translated_answers(): void
    {
        $item = PaymentFormItem::query()->create([
            'payment_type' => PaymentManager::TYPE_SUBMISSION_FEE,
            'type' => PaymentFormItem::TYPE_RADIO,
        ]);
        $this->storeTranslations($item, [
            'name' => ['en' => 'Diet', 'id' => 'Makanan'],
            'response_options' => ['en' => ['Vegetarian', 'Any'], 'id' => ['Vegetarian', 'Apa saja']],
        ]);

        $fee = PaymentFee::query()->create([
            'name' => 'Submission fee',
            'type' => PaymentManager::TYPE_SUBMISSION_FEE,
            'amount' => 100,
            'currency' => 'usd',
            'is_active' => true,
        ]);
        $fee->setTranslation('name', 'id', 'Biaya pengiriman');

        $submission = $this->makePaper($this->author, 'en', ['title' => ['en' => 'Coral reefs']]);
        $payment = PaymentManager::get()->queue(
            $submission,
            $fee,
            $this->author,
            PaymentManager::TYPE_SUBMISSION_FEE,
            'Coral reefs',
            'https://example.test',
        );
        $payment->setMeta('form_responses', [$item->getKey() => 'Any']);

        app()->setLocale('id');

        Livewire::test(PaymentDetail::class, ['record' => $payment])
            ->assertSee('Makanan')
            ->assertSee('Apa saja')
            ->assertSee('Biaya pengiriman');

        app()->setLocale('en');

        Livewire::test(PaymentDetail::class, ['record' => $payment])
            ->assertSee('Diet')
            ->assertSee('Any')
            ->assertDontSee('Apa saja');
    }

    protected function makeSubmissionFormItem(): SubmissionFormItem
    {
        $item = SubmissionFormItem::query()->create(['type' => SubmissionFormItem::TYPE_RADIO]);

        $this->storeTranslations($item, [
            'name' => ['en' => 'Presentation', 'id' => 'Presentasi'],
            'description' => ['en' => 'How you present', 'id' => 'Cara presentasi'],
            'response_options' => ['en' => ['Oral', 'Poster'], 'id' => ['Lisan', 'Poster digital']],
        ]);

        return $item;
    }

    /**
     * Store values per locale the way the form item forms do.
     *
     * @param  array<string, array<string, mixed>>  $values
     */
    protected function storeTranslations(Model $item, array $values): void
    {
        $data = $item->syncTranslatableState(['meta' => $values]);

        $item->setManyMeta($data['meta']);
        $item->persistTranslatableState();
    }
}
