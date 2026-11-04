<?php

namespace Tests\Feature;

use App\Mail\Templates\ParticipantPaymentMail;
use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Notifications\ParticipantPayment;
use App\Panel\ScheduledConference\Livewire\ParticipantPaymentFeeTable;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FreeParticipantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private ScheduledConference $scheduled;

    protected function setUp(): void
    {
        parent::setUp();
        app()->getSite();
        $conference = Conference::factory()->create(['path' => 'free-registration']);
        $this->scheduled = ScheduledConference::factory()->create(['conference_id' => $conference->id, 'path' => '2026']);
        app()->setCurrentConferenceId($conference->id);
        app()->setCurrentScheduledConferenceId($this->scheduled->id);
        app()->scopeCurrentConference();
        app()->scopeCurrentScheduledConference();
        $this->scheduled->setManyMeta(['allow_registration' => true, 'participant_payment' => true, 'invoice_enable' => true, 'receipt_enable' => true, 'participant_payment_auto_notify' => true]);
        $role = Role::withoutGlobalScopes()->firstOrCreate(['name' => UserRole::Participant->value, 'guard_name' => 'web', 'conference_id' => 0, 'scheduled_conference_id' => 0]);
        $user = User::factory()->create(['email' => 'free-participant@example.test', 'password' => 'registration-test']);
        $user->assignRole($role);
        $this->actingAs($user);
        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Notification::fake();
        Storage::fake('private-files');
        Storage::fake('tmp-for-tests');
    }

    public static function locales(): array
    {
        return [['en'], ['fr']];
    }

    private function customField(int $type = PaymentFormItem::TYPE_TEXT): PaymentFormItem
    {
        $field = PaymentFormItem::create(['payment_type' => PaymentManager::TYPE_PARTICIPANT_FEE, 'type' => $type, 'required' => true]);
        $field->setMeta('name', 'QA required field');

        return $field;
    }

    private function fee(array $attributes = []): PaymentFee
    {
        return PaymentFee::create(array_merge(['name' => 'Paid fee', 'type' => PaymentManager::TYPE_PARTICIPANT_FEE, 'amount' => 100, 'currency' => 'usd', 'is_active' => true], $attributes));
    }

    /** @dataProvider locales */
    public function test_no_fee_registration_keeps_answers_without_an_invoice_or_fee(string $locale): void
    {
        app()->setLocale($locale);
        $field = $this->customField();
        Livewire::test(ParticipantRegistration::class)->assertSee(__('participant_registration.free_notice'))
            ->set('formData.given_name', 'Free QA')->set('formData.form_responses.'.$field->id, 'My answer')->call('submit')->assertHasNoFormErrors();
        $participant = Participant::firstOrFail();
        $payment = $participant->payment;
        $this->assertNotNull($payment);
        $this->assertTrue($payment->isFreeRegistration());
        $this->assertTrue($payment->isPaid());
        $this->assertNull($payment->paid_at);
        $this->assertTrue(Payment::paid()->whereKey($payment->id)->exists());
        $this->assertFalse(Payment::paid(false)->whereKey($payment->id)->exists());
        $this->assertNull($payment->payment_fee_id);
        $this->assertNull($payment->invoice);
        $this->assertNull($payment->receipt);
        $this->assertSame(0.0, (float) $payment->amount);
        $this->assertSame('My answer', $payment->getFormItemResponse($field));
        $this->assertFalse($payment->ensureInvoice());
        $this->assertDatabaseCount('payment_fees', 0);
        Notification::assertNotSentTo(auth()->user(), ParticipantPayment::class);
        $this->assertFalse(ParticipantRegistration::canAccess());
        Livewire::test(PaymentDetail::class, ['record' => $payment])->assertStatus(200)->assertSee(__('participant_registration.free_notice'));
    }

    /** @dataProvider locales */
    public function test_free_registration_preserves_required_upload(string $locale): void
    {
        app()->setLocale($locale);
        $field = $this->customField(PaymentFormItem::TYPE_UPLOAD);
        Livewire::test(ParticipantRegistration::class)->set('formData.given_name', 'Upload QA')
            ->set('formData.form_responses.'.$field->id, [UploadedFile::fake()->createWithContent('test-document.txt', 'Dummy registration attachment')])
            ->call('submit')->assertHasNoFormErrors();
        $payment = Payment::firstOrFail();
        $media = $payment->getMedia('form_responses.'.$field->id)->sole();
        $this->assertSame('private-files', $media->disk);
        $this->assertSame('Dummy registration attachment', Storage::disk('private-files')->get($media->getPathRelativeToRoot()));
        $this->assertSame([], $payment->getMeta('form_responses'));
    }

    public function test_a_required_custom_field_still_cannot_be_skipped(): void
    {
        $field = $this->customField();
        Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasFormErrors(['form_responses.'.$field->id => 'required']);
        $this->assertDatabaseCount('participants', 0);
    }

    /** @dataProvider locales */
    public function test_paid_registration_requires_a_fee_and_keeps_the_normal_charge(string $locale): void
    {
        app()->setLocale($locale);
        $fee = $this->fee();
        $page = Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasFormErrors(['payment_fee_id' => 'required']);
        $this->assertDatabaseCount('participants', 0);
        $page->set('formData.payment_fee_id', $fee->id)->call('submit')->assertHasNoFormErrors();
        $payment = Payment::firstOrFail();
        $this->assertFalse($payment->isFreeRegistration());
        $this->assertSame(100.0, (float) $payment->amount);
        $this->assertFalse($payment->isPaid());
        $this->assertTrue(Payment::paid(false)->whereKey($payment->id)->exists());
        $this->assertFalse(Payment::paid()->whereKey($payment->id)->exists());
        Notification::assertSentTo(auth()->user(), ParticipantPayment::class);
    }

    public static function unavailableFees(): array
    {
        $cases = [];
        foreach (['en', 'fr'] as $locale) {
            $cases[$locale.' inactive'] = [$locale, ['is_active' => false]];
            $cases[$locale.' future'] = [$locale, ['opened_at' => '2099-01-01', 'closed_at' => '2099-12-31']];
            $cases[$locale.' expired'] = [$locale, ['opened_at' => '2000-01-01', 'closed_at' => '2000-12-31']];
        }

        return $cases;
    }

    /** @dataProvider unavailableFees */
    public function test_an_unavailable_fee_does_not_silently_make_registration_free(string $locale, array $attributes): void
    {
        app()->setLocale($locale);
        $this->fee($attributes);
        Livewire::test(ParticipantRegistration::class)->assertSee(__('participant_registration.fees_unavailable'))
            ->call('submit')->assertHasFormErrors(['payment_fee_id']);
        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_free_registration_is_not_a_pending_payment_in_the_participant_table(): void
    {
        Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasNoFormErrors();
        $payment = Payment::firstOrFail();
        $page = Livewire::test(ParticipantPaymentFeeTable::class)->assertCanSeeTableRecords([$payment]);
        $this->assertFalse($page->instance()->getTable()->isRecordSelectable($payment));
        $this->assertFalse(ParticipantPaymentFeeTable::canSendInvoiceFor($payment));
        $page->filterTable('paid_at', false)->assertCanNotSeeTableRecords([$payment]);
        $page->filterTable('paid_at', true)->assertCanNotSeeTableRecords([$payment]);
    }

    public function test_bulk_billing_skips_free_registrations_and_still_emails_paid_fee_registrations(): void
    {
        Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasNoFormErrors();
        $free = Payment::firstOrFail();
        $fee = $this->fee();
        $participant = Participant::create(['given_name' => 'Paid participant', 'email' => 'paid-participant@example.test']);
        $paid = PaymentManager::get()->queue($participant, $fee, auth()->user(), PaymentManager::TYPE_PARTICIPANT_FEE, 'Paid participant', '/payment');
        Mail::fake();

        // Include a free record explicitly to verify the server-side guard as well as selection in the UI.
        Livewire::test(ParticipantPaymentFeeTable::class)->callTableBulkAction('send-email', [$free, $paid], [
            'subject' => 'Payment request', 'message' => '<p>Please complete your payment.</p>',
        ])->assertHasNoTableBulkActionErrors();

        Mail::assertQueued(ParticipantPaymentMail::class, fn ($mail) => $mail->hasTo($participant->email));
        Mail::assertQueuedCount(1);
        $this->assertFalse($free->fresh()->hasInvoiceBeenSent());
        $this->assertNull($free->fresh()->invoice);
        $this->assertTrue($paid->fresh()->hasInvoiceBeenSent());
    }

    public function test_migration_preserves_existing_paid_records_and_can_revert_without_free_registrations(): void
    {
        $migration = require database_path('migrations/2026_11_04_000001_allow_registration_without_payment_fee.php');
        $migration->down();
        $fee = $this->fee();
        Livewire::test(ParticipantRegistration::class)->set('formData.payment_fee_id', $fee->id)->call('submit')->assertHasNoFormErrors();
        $payment = Payment::firstOrFail();
        $payment->update(['paid_at' => now(), 'payment_method' => 'manual']);
        $before = $payment->fresh()->getRawOriginal();

        $migration->up();
        $this->assertSame($before, $payment->fresh()->getRawOriginal());
        $this->assertTrue(Payment::paid()->whereKey($payment->id)->exists());
        $this->assertFalse(Payment::paid(false)->whereKey($payment->id)->exists());
        $migration->down();
        $this->assertSame($before, $payment->fresh()->getRawOriginal());
        $migration->up();
    }

    public function test_migration_refuses_to_revert_while_free_registrations_exist(): void
    {
        Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasNoFormErrors();
        $migration = require database_path('migrations/2026_11_04_000001_allow_registration_without_payment_fee.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Free registrations exist');
        $migration->down();
    }

    public function test_submission_fees_do_not_turn_a_free_participant_registration_into_a_paid_one(): void
    {
        $this->fee(['type' => PaymentManager::TYPE_SUBMISSION_FEE]);
        Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasNoFormErrors();
        $this->assertTrue(Payment::firstOrFail()->isFreeRegistration());
    }

    public function test_stale_save_does_not_create_duplicate_free_registrations(): void
    {
        $page = Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasNoFormErrors();
        $page->call('submit');
        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_another_event_does_not_add_a_fee_or_required_field_to_this_registration(): void
    {
        $other = ScheduledConference::factory()->create(['conference_id' => $this->scheduled->conference_id, 'path' => 'other-event']);
        app()->setCurrentScheduledConferenceId($other->id);
        $this->fee();
        $otherField = $this->customField();
        $otherField->setMeta('name', 'Other event required field');
        app()->setCurrentScheduledConferenceId($this->scheduled->id);
        Livewire::test(ParticipantRegistration::class)->assertDontSee('Other event required field')->call('submit')->assertHasNoFormErrors();
        $this->assertTrue(Payment::firstOrFail()->isFreeRegistration());
    }

    public function test_free_registration_does_not_expose_native_invoice_or_receipt(): void
    {
        Livewire::test(ParticipantRegistration::class)->call('submit')->assertHasNoFormErrors();
        $payment = Payment::firstOrFail();
        $route = new \Illuminate\Routing\Route('GET', '/qa-free-document', fn () => null);
        $route->bind(\Illuminate\Http\Request::create('/qa-free-document'));
        $route->setParameter('record', $payment);
        \Illuminate\Support\Facades\Route::partialMock()->shouldReceive('getCurrentRoute')->andReturn($route);
        foreach (['invoice' => \App\Panel\ScheduledConference\Pages\Invoice::class, 'receipt' => \App\Panel\ScheduledConference\Pages\Receipt::class] as $kind => $controller) {
            try {
                (app($controller))();
                $this->fail('Free registration should not produce '.$kind);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
    }
}
