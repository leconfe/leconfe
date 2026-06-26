<?php

namespace Tests\Feature;

use App\Mail\Templates\TemplateMailable;
use App\Mail\Templates\TestMail;
use App\Models\Conference;
use App\Models\MailTemplate;
use App\Models\ScheduledConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class ScheduledConferenceMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_keeps_using_conference_name_without_scheduled_conference_context(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Parent Conference',
            'path' => 'parent-conference',
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId(0);

        MailTemplate::query()->create([
            'conference_id' => $conference->getKey(),
            'mailable' => TestMail::class,
            'subject' => 'Mail from {{ conferenceName }}',
            'html_template' => 'Body',
            'text_template' => 'Body',
        ]);

        $mail = new TestMail;

        $this->assertSame('Parent Conference', $mail->buildViewData()['conferenceName']);
        $this->assertSame('Mail from Parent Conference', $this->renderSubject($mail));
    }

    public function test_subject_uses_scheduled_conference_name_in_scheduled_conference_context(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Parent Conference',
            'path' => 'parent-conference',
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Scheduled Conference 2026',
            'path' => 'scheduled-2026',
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDay()->toDateString(),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        MailTemplate::query()->create([
            'conference_id' => $conference->getKey(),
            'mailable' => TestMail::class,
            'subject' => 'Mail from {{ conferenceName }}',
            'html_template' => 'Body',
            'text_template' => 'Body',
        ]);

        $this->assertSame(
            'Mail from Scheduled Conference 2026',
            $this->renderSubject(new TestMail)
        );
    }

    private function renderSubject(TemplateMailable $mail): string
    {
        $message = new class
        {
            public string $subject = '';

            public function subject(string $subject): self
            {
                $this->subject = $subject;

                return $this;
            }
        };

        $method = (new ReflectionClass(TemplateMailable::class))->getMethod('buildSubject');
        $method->setAccessible(true);
        $method->invoke($mail, $message);

        return $message->subject;
    }
}
