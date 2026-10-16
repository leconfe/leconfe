<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Permissions\PermissionPopulateAction;
use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\PaymentFee;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Timeline;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\PaymentFeeTable;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class PaymentFeeTranslationTest extends TestCase
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

        // Permissions are covered elsewhere; these tests cover the fee translations.
        Gate::before(fn () => true);
        PermissionPopulateAction::run();

        $this->makeTrack();
        $this->author = $this->makeAuthor();
        $this->actingAs($this->author);
    }

    public function test_the_fee_form_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->loadTable()
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.name.id')
            ->assertSeeHtml('mountedTableActionsData.0.meta.description.id')
            ->setTableActionData([
                'name' => ['en' => 'Submission fee', 'id' => 'Biaya pengiriman'],
                'limit' => 0,
                'currency' => 'usd',
                'amount' => 100,
                'is_active' => true,
                'meta' => [
                    'description' => ['en' => 'Paid once', 'id' => 'Dibayar sekali'],
                    'additional_items' => [
                        $this->addOn(['en' => 'Lunch', 'id' => 'Makan siang'], ['en' => 'Daily lunch', 'id' => 'Makan siang harian'], 10),
                        $this->addOn(['en' => 'Dinner', 'id' => 'Makan malam'], ['en' => '', 'id' => ''], 20),
                    ],
                ],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $fee = PaymentFee::query()->sole();

        $this->assertSame('Submission fee', $fee->name);
        $this->assertSame('Biaya pengiriman', $fee->translated('name', 'id'));
        $this->assertSame('Paid once', $fee->getMeta('description'));
        $this->assertSame('Dibayar sekali', $fee->translated('description', 'id'));
        $this->assertSame(['Lunch', 'Dinner'], array_column($fee->getAdditionalItems(), 'name'));
        $this->assertSame(['Makan siang', 'Makan malam'], array_column($fee->getTranslatedAdditionalItems('id'), 'name'));
        $this->assertSame('Makan siang harian', $fee->getTranslatedAdditionalItems('id')[0]['description']);
        $this->assertSame('Daily lunch', $fee->getTranslatedAdditionalItems('en')[0]['description']);

        Livewire::test(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->loadTable()
            ->mountTableAction('edit', $fee)
            ->assertTableActionDataSet([
                'name' => ['en' => 'Submission fee', 'id' => 'Biaya pengiriman'],
                'meta.description' => ['en' => 'Paid once', 'id' => 'Dibayar sekali'],
            ])
            ->setTableActionData([
                'name' => ['en' => 'Submission fee', 'id' => 'Biaya pengiriman makalah'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $fee = PaymentFee::query()->sole();

        $this->assertSame('Biaya pengiriman makalah', $fee->translated('name', 'id'));
        $this->assertSame(['Makan siang', 'Makan malam'], array_column($fee->getTranslatedAdditionalItems('id'), 'name'));
    }

    public function test_add_on_translations_follow_their_item(): void
    {
        $this->enableLanguages(['en', 'id']);

        $fee = $this->makeFee(PaymentManager::TYPE_SUBMISSION_FEE, [
            $this->addOn(['en' => 'Lunch', 'id' => 'Makan siang'], ['en' => 'Daily lunch', 'id' => 'Makan siang harian'], 10),
            $this->addOn(['en' => 'Dinner', 'id' => 'Makan malam'], ['en' => null, 'id' => null], 20),
            $this->addOn(['en' => 'Tour', 'id' => 'Wisata'], ['en' => null, 'id' => null], 30),
        ]);

        $component = Livewire::test(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->loadTable()
            ->mountTableAction('edit', $fee);

        $items = $component->get('mountedTableActionsData.0.meta.additional_items');
        $this->assertSame(['Lunch', 'Dinner', 'Tour'], array_column(array_column($items, 'name'), 'en'));
        $this->assertSame(['Makan siang', 'Makan malam', 'Wisata'], array_column(array_column($items, 'name'), 'id'));

        $component
            ->set('mountedTableActionsData.0.meta.additional_items', array_reverse($items, true))
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $fee = PaymentFee::query()->find($fee->getKey());

        $this->assertSame(['Tour', 'Dinner', 'Lunch'], array_column($fee->getAdditionalItems(), 'name'));
        $this->assertSame(['Wisata', 'Makan malam', 'Makan siang'], array_column($fee->getTranslatedAdditionalItems('id'), 'name'));
        $this->assertSame('Makan siang harian', $fee->getTranslatedAdditionalItems('id')[2]['description']);

        // Renaming an item moves its translations to the item's new key.
        $component = Livewire::test(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->loadTable()
            ->mountTableAction('edit', $fee);

        $items = $component->get('mountedTableActionsData.0.meta.additional_items');
        $lunch = array_key_last($items);
        $oldKey = $items[$lunch]['original_key'];
        $tour = array_key_first($items);

        $component
            ->set("mountedTableActionsData.0.meta.additional_items.{$lunch}.name.en", 'Buffet lunch')
            ->set('mountedTableActionsData.0.meta.additional_items', collect($component->get('mountedTableActionsData.0.meta.additional_items'))->except($tour)->all())
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $fee = PaymentFee::query()->find($fee->getKey());
        $items = $fee->getTranslatedAdditionalItems('id');

        $this->assertSame(['Dinner', 'Buffet lunch'], array_column($fee->getAdditionalItems(), 'name'));
        $this->assertSame(['Makan malam', 'Makan siang'], array_column($items, 'name'));
        $this->assertNotSame($oldKey, $items[1]['key']);
        $this->assertSame(0, $fee->translate()->where('key', 'like', "additional_items.{$oldKey}.%")->count());
        $this->assertSame(0, $fee->translate()->where('key', 'like', 'additional_items.'.PaymentFee::additionalItemKey(['name' => 'Tour', 'amount' => 30]).'.%')->count());
    }

    public function test_renaming_an_item_with_one_language_keeps_its_translations(): void
    {
        $this->enableLanguages(['en', 'id']);

        $fee = $this->makeFee(PaymentManager::TYPE_SUBMISSION_FEE, [
            $this->addOn(['en' => 'Lunch', 'id' => 'Makan siang'], ['en' => null, 'id' => null], 10),
        ]);

        $this->enableLanguages(['en']);

        $component = Livewire::test(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->loadTable()
            ->mountTableAction('edit', $fee);

        $item = array_key_first($component->get('mountedTableActionsData.0.meta.additional_items'));

        $component
            ->assertSet("mountedTableActionsData.0.meta.additional_items.{$item}.name", 'Lunch')
            ->set("mountedTableActionsData.0.meta.additional_items.{$item}.name", 'Buffet lunch')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $fee = PaymentFee::query()->find($fee->getKey());

        $this->assertSame('Buffet lunch', $fee->getTranslatedAdditionalItems('en')[0]['name']);
        $this->assertSame('Makan siang', $fee->getTranslatedAdditionalItems('id')[0]['name']);
    }

    public function test_renaming_an_item_keeps_the_translations_of_disabled_languages(): void
    {
        $this->enableLanguages(['en', 'id', 'fr']);

        $fee = $this->makeFee(PaymentManager::TYPE_SUBMISSION_FEE, [
            $this->addOn(
                ['en' => 'Lunch', 'id' => 'Makan siang', 'fr' => 'Déjeuner'],
                ['en' => 'Daily lunch', 'id' => 'Makan siang harian', 'fr' => 'Repas'],
                10,
            ),
        ]);

        $this->enableLanguages(['en', 'id']);

        $component = Livewire::test(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->loadTable()
            ->mountTableAction('edit', $fee);

        $item = array_key_first($component->get('mountedTableActionsData.0.meta.additional_items'));

        $component
            ->set("mountedTableActionsData.0.meta.additional_items.{$item}.name.en", 'Buffet lunch')
            ->set("mountedTableActionsData.0.meta.additional_items.{$item}.description.id", 'Makan siang prasmanan')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->enableLanguages(['en', 'id', 'fr']);

        $fee = PaymentFee::query()->find($fee->getKey());

        $this->assertSame('Buffet lunch', $fee->getTranslatedAdditionalItems('en')[0]['name']);
        $this->assertSame('Makan siang', $fee->getTranslatedAdditionalItems('id')[0]['name']);
        $this->assertSame('Makan siang prasmanan', $fee->getTranslatedAdditionalItems('id')[0]['description']);
        $this->assertSame('Déjeuner', $fee->getTranslatedAdditionalItems('fr')[0]['name']);
        $this->assertSame('Repas', $fee->getTranslatedAdditionalItems('fr')[0]['description']);
        $this->assertSame(0, $fee->translate()->where('key', 'like', 'additional_items.'.PaymentFee::additionalItemKey(['name' => 'Lunch', 'amount' => 10, 'description' => 'Daily lunch']).'.%')->count());
    }

    public function test_the_create_submission_form_shows_the_fee_in_the_session_language(): void
    {
        $this->enableLanguages(['en', 'id', 'fr']);
        $this->scheduledConference->setMeta('submission_payment', true);

        Timeline::query()->create([
            'name' => 'Submission open',
            'date' => now()->subDay(),
            'type' => Timeline::TYPE_SUBMISSION_OPEN,
        ]);

        $fee = $this->makeFee(PaymentManager::TYPE_SUBMISSION_FEE, [
            $this->addOn(['en' => 'Lunch', 'id' => 'Makan siang'], ['en' => 'Daily lunch', 'id' => 'Makan siang harian'], 10),
            $this->addOn(['en' => 'Dinner', 'id' => ''], ['en' => 'Evening meal', 'id' => ''], 20),
        ]);

        app()->setLocale('id');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Biaya pengiriman')
            ->assertDontSee('Submission fee')
            ->set('data.payment_fee_id', $fee->getKey())
            ->assertSee('Makan siang')
            ->assertSee('Makan siang harian')
            ->assertSee('Dinner')
            ->assertSee('Evening meal')
            ->assertDontSee('Daily lunch')
            ->set('data.meta.title', ['en' => 'Coral reefs', 'id' => ''])
            ->set('data.additional_items.'.$fee->getAdditionalItems()[0]['key'], 2)
            ->set('data.submissionRequirements', true)
            ->set('data.privacy_consent', true)
            ->call('submit')
            ->assertHasNoErrors();

        // Payments store the primary-language text and are shown in the reader's language.
        $payment = Submission::query()->sole()->payment;

        $this->assertSame('Submission fee', $payment->fee->name);
        $this->assertSame('Lunch', $payment->getMeta('additional_items')[0]['name']);
        $this->assertSame(120.0, (float) $payment->amount);
        $this->assertSame('Makan siang', $fee->localizeAdditionalItems($payment->getMeta('additional_items'), 'id')[0]['name']);

        app()->setLocale('fr');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Submission fee')
            ->set('data.payment_fee_id', $fee->getKey())
            ->assertSee('Lunch')
            ->assertDontSee('Makan siang');
    }

    public function test_the_invoice_shows_the_fee_and_add_ons_in_the_session_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $fee = $this->makeFee(PaymentManager::TYPE_SUBMISSION_FEE, [
            $this->addOn(['en' => 'Lunch', 'id' => 'Makan siang'], ['en' => 'Daily lunch', 'id' => 'Makan siang harian'], 10),
        ]);

        $submission = $this->makePaper($this->author, 'en', ['title' => ['en' => 'Coral reefs']]);
        $payment = PaymentManager::get()->queue(
            $submission,
            $fee,
            $this->author,
            PaymentManager::TYPE_SUBMISSION_FEE,
            'Coral reefs',
            'https://example.test',
            $fee->getMeta('description'),
            110,
            'usd',
            null,
            $fee->getSelectedAdditionalItemsFromData(['additional_items' => [$fee->getAdditionalItems()[0]['key'] => 1]]),
            100,
        );

        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route('filament.scheduledConference.pages.invoice', [
                'conference' => $this->conference->path,
                'serie' => $this->scheduledConference->path,
                'record' => $payment->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Biaya pengiriman')
            ->assertSee('Makan siang')
            ->assertSee('Makan siang harian')
            ->assertDontSee('Daily lunch');
    }

    public function test_participant_registration_shows_the_fee_in_the_session_language(): void
    {
        $this->enableLanguages(['en', 'id']);
        $this->scheduledConference->setManyMeta([
            'allow_registration' => true,
            'participant_payment' => true,
        ]);

        $participant = User::query()->create([
            'given_name' => 'Budi',
            'family_name' => 'Santoso',
            'email' => 'budi@example.test',
            'password' => 'password123456',
        ]);
        $participant->assignRole(Role::query()->firstOrCreate([
            'name' => UserRole::Participant->value,
            'guard_name' => 'web',
            'conference_id' => 0,
            'scheduled_conference_id' => 0,
        ]));
        $this->actingAs($participant);

        $fee = $this->makeFee(PaymentManager::TYPE_PARTICIPANT_FEE, [
            $this->addOn(['en' => 'Lunch', 'id' => 'Makan siang'], ['en' => null, 'id' => null], 10),
            $this->addOn(['en' => 'Dinner', 'id' => null], ['en' => null, 'id' => null], 20),
        ], ['en' => 'Registration fee', 'id' => 'Biaya pendaftaran']);

        app()->setLocale('id');

        Livewire::test(ParticipantRegistration::class)
            ->assertSee('Biaya pendaftaran')
            ->assertDontSee('Registration fee')
            ->set('formData.payment_fee_id', $fee->getKey())
            ->assertSee('Makan siang')
            ->assertSee('Dinner');

        app()->setLocale('en');

        Livewire::test(ParticipantRegistration::class)
            ->assertSee('Registration fee')
            ->set('formData.payment_fee_id', $fee->getKey())
            ->assertSee('Lunch')
            ->assertDontSee('Makan siang');
    }

    /**
     * @param  array<string, ?string>  $name
     * @param  array<string, ?string>  $description
     */
    protected function addOn(array $name, array $description, float $amount): array
    {
        return ['name' => $name, 'description' => $description, 'amount' => $amount];
    }

    /**
     * A fee whose name, description and add-on items are stored the way the fee form
     * stores them.
     *
     * @param  array<int, array<string, mixed>>  $additionalItems
     * @param  array<string, ?string>  $name
     */
    protected function makeFee(int $type, array $additionalItems, array $name = ['en' => 'Submission fee', 'id' => 'Biaya pengiriman']): PaymentFee
    {
        $fee = new PaymentFee;
        $fee->type = $type;

        $data = $fee->syncTranslatableState([
            'name' => $name,
            'amount' => 100,
            'currency' => 'usd',
            'limit' => 0,
            'is_active' => true,
            'meta' => [
                'description' => ['en' => 'Paid once', 'id' => 'Dibayar sekali'],
                'additional_items' => $additionalItems,
            ],
        ]);
        $data = $fee->syncAdditionalItemState($data);

        $fee->fill($data)->save();
        $fee->setManyMeta($data['meta']);
        $fee->persistTranslatableState();
        $fee->persistAdditionalItemTranslations();

        return $fee->refresh();
    }
}
