<?php

namespace Tests\Feature\Multilingual;

use App\Mail\Templates\DeclineAbstractMail;
use App\Mail\Templates\ResetPasswordMail;
use App\Mail\Templates\TestMail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\MailTemplate;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Notifications\AbstractDeclined;
use App\Support\Multilingual\RecipientLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mime\Email;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class RecipientLanguageMailTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $indonesianReader;

    protected User $englishReader;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test/submissions/{record}', fn () => null)
            ->name('filament.conference.resources.submissions.view');
        Route::getRoutes()->refreshNameLookups();

        app()->getSite();
        $this->enableLanguages(['en', 'id']);

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);
        $this->scheduledConference->setTranslation('title', 'id', 'Konferensi Tahunan 2026');

        $this->conference->setMeta('settings_mail_header', '<p>English header</p>');
        $this->conference->setMeta('settings_mail_footer', '<p>English footer {{ conferenceName }}</p>');
        $this->conference->setTranslation('settings_mail_header', 'id', '<p>Kepala surat</p>');
        $this->conference->setTranslation('settings_mail_footer', 'id', '<p>Kaki surat {{ conferenceName }}</p>');

        $this->indonesianReader = $this->makeUser('pembaca@example.com', 'id');
        $this->englishReader = $this->makeUser('reader@example.com', 'en');
    }

    public function test_template_email_is_written_in_the_recipients_language(): void
    {
        $this->customizeTestMail();
        Mail::fake();
        App::setLocale('en');

        Mail::to($this->indonesianReader)->send(new TestMail);

        $mail = $this->queued(TestMail::class);

        $this->assertSame('id', $mail->locale);
        $this->assertSame('Halo Konferensi Tahunan 2026', $this->renderSubject($mail));

        $html = $mail->render();

        $this->assertStringContainsString('Isi email untuk Konferensi Tahunan 2026', $html);
        $this->assertStringContainsString('Kepala surat', $html);
        $this->assertStringContainsString('Kaki surat Konferensi Tahunan 2026', $html);
        $this->assertStringContainsString('lang="id"', $html);
        $this->assertStringNotContainsString('English', $html);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_template_email_is_written_in_english_for_an_english_reader_while_the_sender_uses_indonesian(): void
    {
        $this->customizeTestMail();
        Mail::fake();
        App::setLocale('id');

        Mail::to($this->englishReader)->send(new TestMail);

        $mail = $this->queued(TestMail::class);

        $this->assertSame('en', $mail->locale);
        $this->assertSame('Hello Annual Conference 2026', $this->renderSubject($mail));

        $html = $mail->render();

        $this->assertStringContainsString('Body for Annual Conference 2026', $html);
        $this->assertStringContainsString('English header', $html);
        $this->assertStringContainsString('English footer Annual Conference 2026', $html);
        $this->assertStringNotContainsString('Kepala surat', $html);
        $this->assertStringNotContainsString('Isi email', $html);
    }

    public function test_missing_translation_falls_back_to_the_default_language_template(): void
    {
        $template = $this->customizeTestMail(withIndonesian: false);
        $this->assertNull($template->getStoredTranslation('subject', 'id'));

        Mail::fake();

        Mail::to($this->indonesianReader)->send(new TestMail);

        $mail = $this->queued(TestMail::class);

        $this->assertSame('Hello Konferensi Tahunan 2026', $this->renderSubject($mail));
        $this->assertStringContainsString('Body for Konferensi Tahunan 2026', $mail->render());
    }

    public function test_uncustomized_template_renders_its_built_in_default(): void
    {
        Mail::fake();

        Mail::to($this->indonesianReader)->send(new TestMail);

        $mail = $this->queued(TestMail::class);

        $this->assertSame(TestMail::getDefaultSubject(), $this->renderSubject($mail));

        $html = $mail->render();

        $this->assertStringContainsString('This is a test mail from Leconfe', $html);
        $this->assertStringContainsString('Kepala surat', $html);
    }

    public function test_queued_email_keeps_the_recipient_language_through_serialization(): void
    {
        $this->customizeTestMail();
        App::setLocale('en');

        $mailable = (new TestMail)->to($this->indonesianReader);
        $restored = unserialize(serialize($mailable));

        $this->assertSame('id', $restored->locale);
        $this->assertStringContainsString('Isi email untuk Konferensi Tahunan 2026', $restored->render());

        Mail::to($this->indonesianReader)->send(new TestMail);

        $message = $this->lastSentMessage();

        $this->assertSame('Halo Konferensi Tahunan 2026', $message->getSubject());
        $this->assertStringContainsString('Isi email untuk Konferensi Tahunan 2026', $message->getHtmlBody());
        $this->assertStringContainsString('Kaki surat Konferensi Tahunan 2026', $message->getHtmlBody());
        $this->assertSame('en', app()->getLocale());
    }

    public function test_queued_email_is_rendered_for_its_conference_without_the_sender_context(): void
    {
        $otherConference = $this->makeConference('Other Conference', 'other');
        MailTemplate::query()->create([
            'conference_id' => $otherConference->getKey(),
            'mailable' => TestMail::class,
            'subject' => 'Other conference subject',
            'html_template' => '<p>Other conference body</p>',
        ]);
        $this->customizeTestMail();
        App::setLocale('en');

        $payload = serialize((new TestMail)->to($this->indonesianReader));

        app()->withConferenceContext(null, null, function () use ($payload) {
            $this->assertNull(app()->getCurrentConference());
            $this->assertNull(app()->getCurrentScheduledConference());

            $restored = unserialize($payload);
            $html = $restored->render();

            $this->assertStringContainsString('Isi email untuk Konferensi Tahunan 2026', $html);
            $this->assertStringContainsString('Kepala surat', $html);
            $this->assertStringContainsString('Kaki surat Konferensi Tahunan 2026', $html);
            $this->assertStringNotContainsString('Other conference', $html);

            unserialize($payload)->send(app('mail.manager'));

            $message = $this->lastSentMessage();

            $this->assertSame('Halo Konferensi Tahunan 2026', $message->getSubject());
            $this->assertStringContainsString('Kaki surat Konferensi Tahunan 2026', $message->getHtmlBody());
            $this->assertSame('Konferensi Tahunan 2026', $message->getFrom()[0]->getName());
            $this->assertNull(app()->getCurrentConference());
        });

        $this->assertSame($this->conference->getKey(), app()->getCurrentConferenceId());
        $this->assertSame($this->scheduledConference->getKey(), app()->getCurrentScheduledConferenceId());
    }

    public function test_password_reset_email_names_the_site_in_the_recipients_language(): void
    {
        MailTemplate::query()->create([
            'conference_id' => $this->conference->getKey(),
            'mailable' => ResetPasswordMail::class,
            'subject' => 'Reset password for {{ siteName }}',
            'html_template' => '<p>Reset your password for {{ siteName }}</p>',
        ]);
        App::setLocale('en');

        $mailable = (new ResetPasswordMail($this->indonesianReader))
            ->to($this->indonesianReader->email)
            ->locale(RecipientLocale::for($this->indonesianReader->email));

        $this->assertSame('id', $mailable->locale);
        $this->assertStringContainsString('Reset your password for Konferensi Tahunan 2026', $mailable->render());

        $payload = serialize($mailable);

        app()->withConferenceContext(null, null, function () use ($payload) {
            $this->assertStringContainsString('Reset your password for Konferensi Tahunan 2026', unserialize($payload)->render());

            unserialize($payload)->send(app('mail.manager'));
        });

        $this->assertSame('Reset password for Konferensi Tahunan 2026', $this->lastSentMessage()->getSubject());

        Mail::fake();

        Mail::to($this->englishReader->email)
            ->locale(RecipientLocale::for($this->englishReader->email))
            ->send(new ResetPasswordMail($this->englishReader));

        $english = $this->queued(ResetPasswordMail::class);

        $this->assertStringContainsString('Reset your password for Annual Conference 2026', $english->render());
    }

    public function test_notification_is_sent_in_the_recipients_language(): void
    {
        $submission = $this->makeSubmission($this->indonesianReader);
        Notification::fake();
        App::setLocale('en');

        $this->indonesianReader->notify(new AbstractDeclined($submission, channels: ['mail']));

        Notification::assertSentTo(
            $this->indonesianReader,
            AbstractDeclined::class,
            fn ($notification, $channels, $notifiable, $locale) => $locale === 'id',
        );
    }

    public function test_notification_email_is_written_in_the_recipients_language(): void
    {
        $submission = $this->makeSubmission($this->indonesianReader);
        $this->customizeDeclineAbstractMail();

        App::setLocale('en');

        $this->indonesianReader->notify(new AbstractDeclined($submission, channels: ['mail']));

        $message = $this->lastSentMessage();

        $this->assertSame('Ditolak di Konferensi Tahunan 2026', $message->getSubject());
        $this->assertStringContainsString('Mohon maaf, Rina Pembaca', $message->getHtmlBody());
        $this->assertStringContainsString('Kepala surat', $message->getHtmlBody());
        $this->assertSame('en', app()->getLocale());
    }

    public function test_queued_notification_email_is_written_for_its_conference_by_a_worker_without_the_sender_context(): void
    {
        config(['queue.default' => 'database']);
        $submission = $this->makeSubmission($this->indonesianReader);
        $this->customizeDeclineAbstractMail();
        MailTemplate::query()->create([
            'conference_id' => 0,
            'mailable' => DeclineAbstractMail::class,
            'subject' => 'Site-wide subject',
            'html_template' => '<p>Site-wide body</p>',
        ]);
        App::setLocale('en');

        $this->indonesianReader->notify(new AbstractDeclined($submission, channels: ['mail']));

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame([], app('mailer')->getSymfonyTransport()->messages()->all());

        $this->runQueuedJobsWithoutConferenceContext();

        $message = $this->lastSentMessage();

        $this->assertSame('Ditolak di Konferensi Tahunan 2026', $message->getSubject());
        $this->assertStringContainsString('Mohon maaf, Rina Pembaca', $message->getHtmlBody());
        $this->assertStringContainsString('Kepala surat', $message->getHtmlBody());
        $this->assertStringContainsString('Kaki surat Konferensi Tahunan 2026', $message->getHtmlBody());
        $this->assertStringNotContainsString('Site-wide', $message->getHtmlBody());
        $this->assertSame('Konferensi Tahunan 2026', $message->getFrom()[0]->getName());
        $this->assertSame('en', app()->getLocale());
    }

    public function test_email_built_by_a_queued_job_is_written_for_the_conference_the_job_came_from(): void
    {
        config(['queue.default' => 'database']);
        $this->customizeTestMail();
        $reader = $this->indonesianReader;
        App::setLocale('en');

        dispatch(function () use ($reader) {
            Mail::to($reader)->send(new TestMail);
        });

        $this->runQueuedJobsWithoutConferenceContext();

        $message = $this->lastSentMessage();

        $this->assertSame('Halo Konferensi Tahunan 2026', $message->getSubject());
        $this->assertStringContainsString('Isi email untuk Konferensi Tahunan 2026', $message->getHtmlBody());
        $this->assertStringContainsString('Kepala surat', $message->getHtmlBody());
        $this->assertStringContainsString('Kaki surat Konferensi Tahunan 2026', $message->getHtmlBody());
    }

    public function test_variables_from_translated_content_follow_the_recipient_language(): void
    {
        $submission = $this->makeSubmission($this->englishReader);
        Mail::fake();
        App::setLocale('en');

        Mail::to($this->indonesianReader)->send(new DeclineAbstractMail($submission));
        Mail::to($this->englishReader)->send(new DeclineAbstractMail($submission));

        [$indonesian, $english] = Mail::queued(DeclineAbstractMail::class)->values()->all();

        $this->assertStringContainsString('Konferensi Tahunan 2026', $this->renderSubject($indonesian));
        $this->assertStringContainsString('Konferensi Tahunan 2026', $indonesian->render());
        $this->assertStringContainsString('Annual Conference 2026', $this->renderSubject($english));
        $this->assertStringNotContainsString('Konferensi', $english->render());
    }

    public function test_group_send_writes_one_message_per_language(): void
    {
        Mail::fake();

        $secondIndonesianReader = $this->makeUser('pembaca2@example.com', 'id');

        RecipientLocale::send(
            collect([$this->indonesianReader, $this->englishReader, $secondIndonesianReader]),
            fn () => new TestMail,
        );

        Mail::assertQueuedCount(2);
        Mail::assertQueued(TestMail::class, fn (TestMail $mail) => $mail->locale === 'id'
            && $mail->hasTo('pembaca@example.com')
            && $mail->hasTo('pembaca2@example.com')
            && ! $mail->hasTo('reader@example.com'));
        Mail::assertQueued(TestMail::class, fn (TestMail $mail) => $mail->locale === 'en'
            && $mail->hasTo('reader@example.com'));
    }

    public function test_plain_address_recipients_get_their_language_or_the_default(): void
    {
        $this->assertSame('id', RecipientLocale::for('pembaca@example.com'));
        $this->assertSame('id', RecipientLocale::for('PEMBACA@example.com'));
        $this->assertSame('en', RecipientLocale::for('stranger@example.com'));
        $this->assertSame('en', RecipientLocale::for([$this->indonesianReader, $this->englishReader]));
        $this->assertSame('id', RecipientLocale::for([$this->indonesianReader, 'pembaca@example.com']));
        $this->assertSame('id', RecipientLocale::for((object) ['email' => 'pembaca@example.com']));
        $this->assertSame('en', RecipientLocale::for(null));

        $this->enableLanguages(['en', 'id'], 'id');

        $this->assertSame('id', RecipientLocale::for('stranger@example.com'));
    }

    protected function customizeTestMail(bool $withIndonesian = true): MailTemplate
    {
        $template = MailTemplate::query()->create([
            'conference_id' => $this->conference->getKey(),
            'mailable' => TestMail::class,
            'subject' => 'Hello {{ conferenceName }}',
            'html_template' => '<p>Body for {{ conferenceName }}</p>',
        ]);

        if ($withIndonesian) {
            $template->setTranslation('subject', 'id', 'Halo {{ conferenceName }}');
            $template->setTranslation('html_template', 'id', '<p>Isi email untuk {{ conferenceName }}</p>');
        }

        return $template;
    }

    protected function customizeDeclineAbstractMail(): MailTemplate
    {
        $template = MailTemplate::query()->create([
            'conference_id' => $this->conference->getKey(),
            'mailable' => DeclineAbstractMail::class,
            'subject' => 'Declined at {{ Conference Title }}',
            'html_template' => '<p>Sorry, {{ Submission Author }}</p>',
        ]);
        $template->setTranslation('subject', 'id', 'Ditolak di {{ Conference Title }}');
        $template->setTranslation('html_template', 'id', '<p>Mohon maaf, {{ Submission Author }}</p>');

        return $template;
    }

    /**
     * Work off the database queue the way a queue worker does, with no conference context
     * of its own, and check that the sender's context is back afterwards.
     */
    protected function runQueuedJobsWithoutConferenceContext(): void
    {
        app()->withConferenceContext(null, null, function () {
            $this->assertNull(app()->getCurrentConference());

            $worker = app('queue.worker');
            $options = new WorkerOptions(maxTries: 1);

            for ($run = 0; $run < 10 && DB::table('jobs')->exists(); $run++) {
                $worker->runNextJob('database', 'default', $options);

                $this->assertNull(app()->getCurrentConference());
                $this->assertNull(app()->getCurrentScheduledConferenceId());
            }
        });

        $this->assertFalse(DB::table('jobs')->exists(), 'The queue was not worked off.');
        $this->assertSame([], DB::table('failed_jobs')->pluck('exception')->all());
        $this->assertSame($this->conference->getKey(), app()->getCurrentConferenceId());
        $this->assertSame($this->scheduledConference->getKey(), app()->getCurrentScheduledConferenceId());
    }

    protected function makeUser(string $email, ?string $locale): User
    {
        return User::query()->create([
            'given_name' => $locale === 'id' ? 'Rina' : 'Robin',
            'family_name' => $locale === 'id' ? 'Pembaca' : 'Reader',
            'email' => $email,
            'password' => 'password123456',
            'locale' => $locale,
        ]);
    }

    protected function makeSubmission(User $author): Submission
    {
        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'Track',
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);
        $submission->setMeta('title', 'Rice terraces');

        return $submission;
    }

    /**
     * @template T of Mailable
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function queued(string $class): Mailable
    {
        $mail = Mail::queued($class)->first();
        $this->assertNotNull($mail, "{$class} was not queued.");

        return $mail;
    }

    /**
     * The subject as built while the mailable's language is active, like Mailable::send() does.
     */
    protected function renderSubject(Mailable $mail): string
    {
        $message = new class
        {
            public ?string $subject = null;

            public function subject(string $subject): static
            {
                $this->subject = $subject;

                return $this;
            }
        };

        $previous = App::getLocale();
        App::setLocale($mail->locale ?? $previous);

        try {
            (fn () => $this->buildSubject($message))->call($mail);
        } finally {
            App::setLocale($previous);
        }

        return (string) $message->subject;
    }

    protected function lastSentMessage(): Email
    {
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertNotEmpty($messages, 'No email was sent.');

        return $messages->last()->getOriginalMessage();
    }
}
