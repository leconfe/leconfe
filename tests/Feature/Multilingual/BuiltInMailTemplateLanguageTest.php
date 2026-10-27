<?php

namespace Tests\Feature\Multilingual;

use App\Mail\Templates\AcceptPaperMail;
use App\Mail\Templates\DeclinePaperMail;
use App\Mail\Templates\PublishSubmissionMail;
use App\Mail\Templates\ReviewCompleteMail;
use App\Mail\Templates\ReviewerAcceptedInvitationMail;
use App\Mail\Templates\ReviewerInvitationMail;
use App\Mail\Templates\TemplateMailable;
use App\Models\Conference;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\MailTemplate;
use App\Models\Review;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\Conference\Livewire\EmailSetting;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class BuiltInMailTemplateLanguageTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $frenchReader;

    protected User $englishReader;

    protected Submission $submission;

    protected Review $review;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['view' => '/test/submissions/{record}', 'reviewer-invitation' => '/test/submissions/{record}/invitation'] as $page => $uri) {
            Route::get($uri, fn () => null)->name("filament.conference.resources.submissions.{$page}");
        }
        Route::getRoutes()->refreshNameLookups();

        app()->getSite();
        $this->enableLanguages(['en', 'fr']);

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        $this->frenchReader = $this->makeUser('Camille', 'lectrice@example.com', 'fr');
        $this->englishReader = $this->makeUser('Robin', 'reader@example.com', 'en');

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'Track',
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $this->submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $this->frenchReader->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);
        $this->submission->setMeta('title', 'Rice terraces');

        $this->review = Review::query()->create([
            'submission_id' => $this->submission->getKey(),
            'user_id' => $this->frenchReader->getKey(),
        ]);
        $this->review->setManyMeta(['response_due_date' => '2026-11-10', 'review_due_date' => '2026-11-30']);

        App::setLocale('en');
    }

    public function test_built_in_editorial_emails_are_written_in_each_recipients_language(): void
    {
        $expected = [
            ReviewerInvitationMail::class => [
                'fr' => ['Vous avez été désigné comme évaluateur', 'Nous souhaitons vous inviter à évaluer l’article « Rice terraces »'],
                'en' => ['You have been assigned as a reviewer', 'We would like to invite you to review the paper "Rice terraces,"'],
            ],
            ReviewerAcceptedInvitationMail::class => [
                'fr' => ['Invitation acceptée par l’évaluateur', 'L’évaluateur Camille Lecteur a accepté l’invitation à évaluer la soumission Rice terraces'],
                'en' => ['Reviewer Accepted Invitation', 'Reviewer Camille Lecteur has accepted the invitation to review the submission Rice terraces'],
            ],
            ReviewCompleteMail::class => [
                'fr' => ['L’évaluateur Camille Lecteur a terminé une évaluation de la soumission Rice terraces', 'voir la soumission'],
                'en' => ['Reviewer Camille Lecteur has completed a review on submission Rice terraces', 'View Submission'],
            ],
            AcceptPaperMail::class => [
                'fr' => ['Votre soumission Rice terraces à Annual Conference 2026 est acceptée.', 'Notre décision est d’<b>accepter la soumission</b>.'],
                'en' => ['Your submission Rice terraces on Annual Conference 2026 is accepted.', 'Our decision is to <b>accept the submission</b>.'],
            ],
            DeclinePaperMail::class => [
                'fr' => ['Votre soumission Rice terraces à Annual Conference 2026 est refusée.', 'Notre décision est de <b>refuser la soumission</b>.'],
                'en' => ['Your submission Rice terraces on Annual Conference 2026 is declined.', 'Our decision is to <b>decline the submission</b>.'],
            ],
            PublishSubmissionMail::class => [
                'fr' => ['Soumission publiée', 'Votre soumission « Rice terraces » a été publiée sur Annual Conference 2026'],
                'en' => ['Submission Published', 'Your submission "Rice terraces" has been published on Annual Conference 2026'],
            ],
        ];

        foreach ($expected as $mailable => $languages) {
            foreach (['fr' => $this->frenchReader, 'en' => $this->englishReader] as $locale => $reader) {
                [$subject, $body] = $languages[$locale];

                $message = $this->send($reader, $this->make($mailable));
                $html = $message->getHtmlBody();

                $this->assertSame($subject, $message->getSubject(), "{$mailable} subject for {$locale}");
                $this->assertStringContainsString($body, $html, "{$mailable} body for {$locale}");
                $this->assertStringContainsString("lang=\"{$locale}\"", $html);
                $this->assertStringContainsString($locale === 'fr'
                    ? 'Gérez facilement votre conférence avec <a href="https://leconfe.com">Leconfe</a>'
                    : 'Easily manage conference platform using <a href="https://leconfe.com">Leconfe</a>', $html, "{$mailable} footer for {$locale}");
            }
        }

        $this->assertSame('en', app()->getLocale());
    }

    public function test_canadian_french_recipients_get_the_french_built_in_email(): void
    {
        $this->enableLanguages(['en', 'fr', 'fr_CA']);
        $reader = $this->makeUser('Gabrielle', 'lectrice-ca@example.com', 'fr_CA');

        $message = $this->send($reader, $this->make(PublishSubmissionMail::class));

        $this->assertSame('Soumission publiée', $message->getSubject());
        $this->assertStringContainsString('Votre soumission « Rice terraces » a été publiée sur Annual Conference 2026', $message->getHtmlBody());
        $this->assertStringContainsString('Gérez facilement votre conférence avec', $message->getHtmlBody());
    }

    public function test_a_customized_template_is_sent_as_the_organiser_wrote_it(): void
    {
        MailTemplate::query()->create([
            'conference_id' => $this->conference->getKey(),
            'mailable' => ReviewCompleteMail::class,
            'subject' => 'Review in for {{ Submission Title }}',
            'html_template' => '<p>Our own wording</p>',
        ]);

        $message = $this->send($this->frenchReader, $this->make(ReviewCompleteMail::class));

        $this->assertSame('Review in for Rice terraces', $message->getSubject());
        $this->assertStringContainsString('Our own wording', $message->getHtmlBody());
        $this->assertStringNotContainsString('évaluation', $message->getHtmlBody());
    }

    public function test_decision_forms_are_prefilled_in_the_authors_language(): void
    {
        $french = MailTemplate::contentFor(AcceptPaperMail::class, 'fr');
        $english = MailTemplate::contentFor(AcceptPaperMail::class, 'en');

        $this->assertSame('Votre soumission {{ Submission Title }} à {{ Conference Title }} est acceptée.', $french['subject']);
        $this->assertStringContainsString('<p>Bonjour {{ Submission Author }},</p>', $french['html_template']);
        $this->assertSame('Your submission {{ Submission Title }} on {{ Conference Title }} is accepted.', $english['subject']);
        $this->assertStringContainsString('<p>Dear {{ Submission Author }},</p>', $english['html_template']);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_template_form_starts_from_the_built_in_text_of_every_language(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Gate::before(fn () => true);
        $this->actingAs($this->englishReader);

        Livewire::test(EmailSetting::class)
            ->mountTableAction('edit', DefaultMailTemplate::query()->where('mailable', PublishSubmissionMail::class)->firstOrFail())
            ->assertTableActionDataSet([
                'subject' => ['en' => 'Submission Published', 'fr' => 'Soumission publiée'],
            ]);
    }

    /**
     * @param  class-string<TemplateMailable>  $mailable
     */
    protected function make(string $mailable): TemplateMailable
    {
        $model = in_array($mailable, [ReviewerInvitationMail::class, ReviewerAcceptedInvitationMail::class, ReviewCompleteMail::class])
            ? $this->review
            : $this->submission;

        return new $mailable($model);
    }

    protected function send(User $reader, TemplateMailable $mailable): Email
    {
        Mail::to($reader)->send($mailable);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertNotEmpty($messages, 'No email was sent.');

        return $messages->last()->getOriginalMessage();
    }

    protected function makeUser(string $givenName, string $email, string $locale): User
    {
        return User::query()->create([
            'given_name' => $givenName,
            'family_name' => 'Lecteur',
            'email' => $email,
            'password' => 'password123456',
            'locale' => $locale,
        ]);
    }
}
