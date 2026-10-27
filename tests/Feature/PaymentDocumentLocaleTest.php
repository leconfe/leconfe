<?php

namespace Tests\Feature;

use App\Managers\PaymentManager;
use App\Models\Conference;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\ScheduledConference\Pages\Invoice;
use App\Panel\ScheduledConference\Pages\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentDocumentLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_follows_the_payer_language(): void
    {
        [$payment, $payer] = $this->makeParticipantPayment(payerLocale: 'fr');

        $this->actingAs($payer)
            ->withoutVite()
            ->get($this->documentUrl(Invoice::class, $payment))
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('Facture - INV-001', false)
            ->assertSee('Facture n° INV-001', false)
            ->assertSee('Date d’inscription : 14 mars 2026 09:30', false)
            ->assertSee('Facturé à')
            ->assertSee('Prix unitaire')
            ->assertDontSee('Invoiced To')
            ->assertDontSee('Unit Price');
    }

    public function test_receipt_follows_the_payer_language(): void
    {
        [$payment, $payer] = $this->makeParticipantPayment(payerLocale: 'fr');

        $this->actingAs($payer)
            ->withoutVite()
            ->get($this->documentUrl(Receipt::class, $payment))
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('Reçu - RCP-001', false)
            ->assertSee('Reçu n° RCP-001', false)
            ->assertSee('Date d’inscription : 14 mars 2026 09:30', false)
            ->assertSee('Bonjour Participant Tester,', false)
            ->assertSee('Merci pour votre paiement.', false)
            ->assertDontSee('Thank you for your payment.');
    }

    public function test_documents_fall_back_to_the_active_language_when_the_payer_has_none(): void
    {
        [$payment, $payer] = $this->makeParticipantPayment(payerLocale: null);

        $this->actingAs($payer)
            ->withoutVite()
            ->get($this->documentUrl(Invoice::class, $payment))
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Invoice No.: INV-001', false)
            ->assertSee('Registration Date: March 14, 2026 9:30 AM', false)
            ->assertSee('Invoiced To');
    }

    public function test_document_date_keeps_the_year_in_the_payer_language(): void
    {
        [$payment, $payer] = $this->makeParticipantPayment(payerLocale: 'fi');

        $this->actingAs($payer)
            ->withoutVite()
            ->get($this->documentUrl(Receipt::class, $payment))
            ->assertOk()
            ->assertSee('<html lang="fi"', false)
            ->assertSee('14. maaliskuuta 2026 09.30', false);
    }

    /**
     * @return array{0: Payment, 1: User}
     */
    protected function makeParticipantPayment(?string $payerLocale): array
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Test Scheduled Conference',
            'path' => 'test',
            'is_published' => true,
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());
        app()->getSite()->setMeta('settings_languages', ['en', 'fr', 'fi']);

        $payer = User::query()->create([
            'given_name' => 'Participant',
            'family_name' => 'Tester',
            'email' => 'participant@example.test',
            'password' => 'password123456',
        ]);
        $payer->forceFill(['locale' => $payerLocale])->save();

        $participant = Participant::withoutGlobalScopes()->create([
            'given_name' => 'Participant',
            'family_name' => 'Tester',
            'email' => $payer->email,
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
        ]);

        $paymentFee = PaymentFee::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'name' => 'Participant Fee',
            'type' => PaymentManager::TYPE_PARTICIPANT_FEE,
            'amount' => 150,
            'currency' => 'usd',
            'is_active' => true,
        ]);

        $payment = PaymentManager::get()->queue(
            $participant,
            $paymentFee,
            $payer,
            PaymentManager::TYPE_PARTICIPANT_FEE,
            $participant->full_name,
            '/participant/'.$participant->getKey(),
            'Participant billing',
        );

        $payment->forceFill([
            'invoice' => 'INV-001',
            'receipt' => 'RCP-001',
            'created_at' => Carbon::parse('2026-03-14 09:30:00'),
        ])->save();

        return [$payment, $payer];
    }

    /**
     * @param  class-string<Invoice|Receipt>  $page
     */
    protected function documentUrl(string $page, Payment $payment): string
    {
        return route($page::getRouteName('scheduledConference'), [
            'conference' => 'test-conference',
            'serie' => 'test',
            'record' => $payment,
        ]);
    }
}
