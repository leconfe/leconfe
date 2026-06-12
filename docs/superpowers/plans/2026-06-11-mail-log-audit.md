# Mail Log Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add audit trail to email sending so every email can be traced to the user who triggered it, and provide a Filament UI to browse/filter email logs.

**Architecture:** Leverages the existing `spatie/laravel-activitylog` infrastructure (`activity_log` table, `Log` class, `LogSentEmail` listener). The `Log` class already supports a `causer` property but it's never populated. We'll fix that, add logging to the 8 missing mail templates, and build a Filament page to browse the logs.

**Tech Stack:** Laravel 10, Filament 3, Spatie Activity Log, Eloquent, MySQL

---

## Current State Analysis

- `activity_log` table has `causer_id` + `causer_type` columns (polymorphic) but they're always NULL for email logs
- `app/Classes/Log.php` has `by(Model $causer)` method but it's never called
- 22/30 mail templates create a `Log` instance; 8 don't log at all
- `app/Listeners/LogSentEmail.php` saves the log on `MessageSent` event
- The `Log` class stores `$causer` as a Model, which may not serialize correctly in queued mails

**Missing mail templates (no logging):**
- `SubmissionPaymentMail`
- `ParticipantPaymentMail`
- `PaymentConfirmedMail`
- `UserPayPaymentMail`
- `TestMail`
- `NewDiscussionTopicMail`
- `RegistrationEnrollMail`
- `SubmissionPayment` (Notification, dispatches `SubmissionPaymentMail`)

---

## File Structure

**Modify:**
- `app/Classes/Log.php` — Add `causerId`/`causerType` scalar properties for queue-safe serialization
- `app/Mail/Templates/SubmissionPaymentMail.php` — Add Log + causer
- `app/Mail/Templates/ParticipantPaymentMail.php` — Add Log + causer
- `app/Mail/Templates/PaymentConfirmedMail.php` — Add Log + causer
- `app/Mail/Templates/UserPayPaymentMail.php` — Add Log + causer
- `app/Mail/Templates/NewDiscussionTopicMail.php` — Add Log + causer
- `app/Mail/Templates/RegistrationEnrollMail.php` — Add Log + causer (if exists)
- `app/Mail/Templates/AcceptAbstractMail.php` — Add causer
- `app/Mail/Templates/AcceptPaperMail.php` — Add causer
- `app/Mail/Templates/DeclineAbstractMail.php` — Add causer
- `app/Mail/Templates/DeclinePaperMail.php` — Add causer
- `app/Mail/Templates/NewAnnouncementMail.php` — Add causer
- `app/Mail/Templates/NewSubmissionMail.php` — Add causer
- `app/Mail/Templates/ParticipantAssignedMail.php` — Add causer
- `app/Mail/Templates/PublishSubmissionMail.php` — Add causer
- `app/Mail/Templates/ResetPasswordMail.php` — Add causer (skip: user-triggered)
- `app/Mail/Templates/ReviewCompleteMail.php` — Add causer
- `app/Mail/Templates/ReviewerAcceptedInvitationMail.php` — Add causer
- `app/Mail/Templates/ReviewerCancelationMail.php` — Add causer
- `app/Mail/Templates/ReviewerDeclinedInvitationMail.php` — Add causer
- `app/Mail/Templates/ReviewerInvitationMail.php` — Add causer
- `app/Mail/Templates/RevisionRequestMail.php` — Add causer
- `app/Mail/Templates/SubmissionWithdrawnMail.php` — Add causer
- `app/Mail/Templates/SubmissionWithdrawnRequestMail.php` — Add causer
- `app/Mail/Templates/ThankAuthorMail.php` — Add causer
- `app/Mail/Templates/UserRoleInvitationMail.php` — Add causer
- `app/Mail/Templates/VerifyUserEmail.php` — Add causer (skip: user-triggered)
- All dispatch sites that call `Mail::to()->send()` or `->notify()` — Pass causer

**Create:**
- `app/Panel/ScheduledConference/Pages/MailLog.php` — Filament page for email log
- `resources/views/panel/scheduledConference/pages/mail-log.blade.php` — Blade view
- `database/migrations/2026_06_11_000000_add_indexes_to_activity_log_table.php` — Performance indexes

---

## Task 1: Make Log class queue-safe with causer tracking

**Files:**
- Modify: `app/Classes/Log.php`
- Test: `tests/Unit/Classes/LogTest.php`

The current `Log` class stores `$causer` as an Eloquent Model. When Mailables are queued, the `SerializesModels` trait only handles top-level model properties — nested Models inside plain objects may not serialize correctly. We need to store the causer as simple scalar values (`causerId`, `causerType`) that serialize reliably.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Classes;

use App\Classes\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_can_set_causer_from_model(): void
    {
        $user = User::factory()->create();
        $submission = \App\Models\Submission::factory()->create();

        $log = Log::make(
            subject: $submission,
            name: 'email',
            description: 'Test email',
        )->by($user);

        $this->assertEquals($user->getKey(), $log->causerId);
        $this->assertEquals(User::class, $log->causerType);
    }

    public function test_log_serializes_causer_as_scalars(): void
    {
        $user = User::factory()->create();
        $submission = \App\Models\Submission::factory()->create();

        $log = Log::make(
            subject: $submission,
            name: 'email',
            description: 'Test email',
        )->by($user);

        $serialized = serialize($log);
        $unserialized = unserialize($serialized);

        $this->assertEquals($user->getKey(), $unserialized->causerId);
        $this->assertEquals(User::class, $unserialized->causerType);
        $this->assertNull($unserialized->causer);
    }

    public function test_log_by_anonymous_sets_null_causer(): void
    {
        $submission = \App\Models\Submission::factory()->create();

        $log = Log::make(
            subject: $submission,
            name: 'email',
            description: 'Test email',
        )->byAnonymous();

        $this->assertNull($log->causerId);
        $this->assertNull($log->causerType);
    }

    public function test_log_save_sets_causer_on_activity(): void
    {
        $user = User::factory()->create();
        $submission = \App\Models\Submission::factory()->create();

        Log::make(
            subject: $submission,
            name: 'email',
            description: 'Test email',
        )->by($user)->save();

        $activity = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', $submission->getMorphClass())
            ->where('subject_id', $submission->getKey())
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($user->getKey(), $activity->causer_id);
        $this->assertEquals(User::class, $activity->causer_type);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LogTest`
Expected: FAIL — `causerId` and `causerType` properties don't exist yet

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Classes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Conditionable;

class Log
{
    public array $properties = [];

    public ?Model $causer = null;

    public ?int $causerId = null;

    public ?string $causerType = null;

    use Conditionable;

    public function __construct(
        public Model $subject,
        public string $name,
        public ?string $description = null,
        public ?string $event = null,
    ) {}

    public static function make(
        Model $subject,
        string $name,
        ?string $description = null,
        ?string $event = null,
    ): self {
        return app(static::class, [
            'subject' => $subject,
            'name' => $name,
            'description' => $description,
            'event' => $event,
        ]);
    }

    public function subject(Model $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function causer(?Model $causer): static
    {
        $this->causer = $causer;
        $this->causerId = $causer?->getKey();
        $this->causerType = $causer ? get_class($causer) : null;

        return $this;
    }

    public function by(?Model $causer): static
    {
        return $this->causer($causer);
    }

    public function byAnonymous(): static
    {
        return $this->causer(null);
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function properties(array $properties)
    {
        $this->properties = $properties;

        return $this;
    }

    public function save(): void
    {
        $causer = $this->resolveCauser();

        activity($this->name)
            ->when(
                $causer,
                fn ($log) => $log->by($causer),
                fn ($log) => $log->byAnonymous()
            )
            ->when(
                $this->properties,
                fn ($log) => $log->withProperties($this->properties)
            )
            ->performedOn($this->subject)
            ->log($this->description);
    }

    protected function resolveCauser(): ?Model
    {
        if ($this->causer) {
            return $this->causer;
        }

        if ($this->causerId && $this->causerType) {
            return $this->causerType::find($this->causerId);
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LogTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Classes/Log.php tests/Unit/Classes/LogTest.php
git commit -m "feat: add queue-safe causer tracking to Log class"
```

---

## Task 2: Add causer to all existing mail templates that already log

**Files:**
- Modify: All 22 mail templates in `app/Mail/Templates/` that already have `public Log $log`

Each template's constructor creates a `Log` but never calls `->by()`. We add `->by(auth()->user())` to every one.

- [ ] **Step 1: Add causer to AcceptAbstractMail**

In `app/Mail/Templates/AcceptAbstractMail.php`, change the Log construction:

```php
// Before:
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Abstract Accepted']),
);

// After:
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Abstract Accepted']),
)->by(auth()->user());
```

- [ ] **Step 2: Add causer to AcceptPaperMail**

Same pattern in `app/Mail/Templates/AcceptPaperMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Accept Paper']),
)->by(auth()->user());
```

- [ ] **Step 3: Add causer to DeclineAbstractMail**

Same pattern in `app/Mail/Templates/DeclineAbstractMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Abstract Declined']),
)->by(auth()->user());
```

- [ ] **Step 4: Add causer to DeclinePaperMail**

Same pattern in `app/Mail/Templates/DeclinePaperMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Decline Paper']),
)->by(auth()->user());
```

- [ ] **Step 5: Add causer to NewAnnouncementMail**

Same pattern in `app/Mail/Templates/NewAnnouncementMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $announcement,
    description: __('general.email_sent', ['name' => 'New Announcement']),
)->by(auth()->user());
```

- [ ] **Step 6: Add causer to NewSubmissionMail**

Same pattern in `app/Mail/Templates/NewSubmissionMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'New Submission']),
)->by(auth()->user());
```

- [ ] **Step 7: Add causer to ParticipantAssignedMail**

Same pattern in `app/Mail/Templates/ParticipantAssignedMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Participant Assigned']),
)->by(auth()->user());
```

- [ ] **Step 8: Add causer to PublishSubmissionMail**

Same pattern in `app/Mail/Templates/PublishSubmissionMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Publish Submission']),
)->by(auth()->user());
```

- [ ] **Step 9: Add causer to ReviewCompleteMail**

Same pattern in `app/Mail/Templates/ReviewCompleteMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Review Complete']),
)->by(auth()->user());
```

- [ ] **Step 10: Add causer to ReviewerAcceptedInvitationMail**

Same pattern in `app/Mail/Templates/ReviewerAcceptedInvitationMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Reviewer Accepted Invitation']),
)->by(auth()->user());
```

- [ ] **Step 11: Add causer to ReviewerCancelationMail**

Same pattern in `app/Mail/Templates/ReviewerCancelationMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Reviewer Cancelation']),
)->by(auth()->user());
```

- [ ] **Step 12: Add causer to ReviewerDeclinedInvitationMail**

Same pattern in `app/Mail/Templates/ReviewerDeclinedInvitationMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Reviewer Declined Invitation']),
)->by(auth()->user());
```

- [ ] **Step 13: Add causer to ReviewerInvitationMail**

Same pattern in `app/Mail/Templates/ReviewerInvitationMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Reviewer Invitation']),
)->by(auth()->user());
```

- [ ] **Step 14: Add causer to RevisionRequestMail**

Same pattern in `app/Mail/Templates/RevisionRequestMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Revision Request']),
)->by(auth()->user());
```

- [ ] **Step 15: Add causer to SubmissionWithdrawnMail**

Same pattern in `app/Mail/Templates/SubmissionWithdrawnMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Submission Withdrawn']),
)->by(auth()->user());
```

- [ ] **Step 16: Add causer to SubmissionWithdrawnRequestMail**

Same pattern in `app/Mail/Templates/SubmissionWithdrawnRequestMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Submission Withdrawn Request']),
)->by(auth()->user());
```

- [ ] **Step 17: Add causer to ThankAuthorMail**

Same pattern in `app/Mail/Templates/ThankAuthorMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $submission,
    description: __('general.email_sent', ['name' => 'Thank Author']),
)->by(auth()->user());
```

- [ ] **Step 18: Add causer to UserRoleInvitationMail**

Same pattern in `app/Mail/Templates/UserRoleInvitationMail.php`:

```php
$this->log = Log::make(
    name: 'email',
    subject: $user,
    description: __('general.email_sent', ['name' => 'User Role Invitation']),
)->by(auth()->user());
```

- [ ] **Step 19: Skip VerifyUserEmail and ResetPasswordMail**

These are user-triggered (self-service) — the causer is the user themselves. Add `->by(auth()->user())` to both for completeness.

- [ ] **Step 20: Commit**

```bash
git add app/Mail/Templates/
git commit -m "feat: add causer tracking to all existing mail templates"
```

---

## Task 3: Add logging to missing mail templates

**Files:**
- Modify: `app/Mail/Templates/SubmissionPaymentMail.php`
- Modify: `app/Mail/Templates/ParticipantPaymentMail.php`
- Modify: `app/Mail/Templates/PaymentConfirmedMail.php`
- Modify: `app/Mail/Templates/UserPayPaymentMail.php`
- Modify: `app/Mail/Templates/NewDiscussionTopicMail.php`

- [ ] **Step 1: Add Log to SubmissionPaymentMail**

```php
<?php

namespace App\Mail\Templates;

use App\Classes\Log;
use App\Mail\Templates\Traits\CanCustomizeTemplate;
use App\Models\Payment;
use App\Models\Submission;

class SubmissionPaymentMail extends TemplateMailable
{
    use CanCustomizeTemplate;

    public Log $log;

    public function __construct(Submission $submission)
    {
        $this->setAdditionalData([
            'Conference Title' => $submission->payment->scheduledConference->title,
            'Submission Author' => $submission->user->full_name,
            'Submission Title' => $submission->getMeta('title'),
            'Submission ID' => $submission->getKey(),
            'Payment Amount' => $submission->payment->getFormattedFee(),
            'Payment Link' => $submission->payment->getPaymentDetailUrl()
        ]);

        $this->log = Log::make(
            name: 'email',
            subject: $submission,
            description: __('general.email_sent', ['name' => 'Submission Payment']),
        )->by(auth()->user());
    }

    public static function getDefaultSubject(): string
    {
        return 'Submission Payment for: {{ Submission Title }} on {{ Conference Title }}';
    }

    public static function getDefaultHtmlTemplate(): string
    {
        return <<<'HTML'
            <p>Dear {{ Submission Author }},</p>
            <p>We would like to inform you that a payment requirement has been added to your submission for {{ Conference Title }}.</p>
            <p>Submission Details:</p>
            <ul>
                <li>Title : <b>{{ Submission Title }}</b></li>
                <li>Reference ID : <b>{{ Submission ID }}</b></li>
                <li>Amount Due : <b>{{ Payment Amount }}</b></li>
            </ul>
            <p>To proceed, please complete your payment by visiting the link below:</p>
            <a href="{{ Payment Link }}">{{ Payment Link }}</a>
            <p>
                If you have already made the payment, kindly disregard this notice.
            </p>
            <p>Thank you for your participation, and we look forward to your contribution to {{ Conference Title }}.</p>
        HTML;
    }

    public static function getDefaultDescription(): string
    {
        return 'Submission Payment email template';
    }
}
```

- [ ] **Step 2: Add Log to ParticipantPaymentMail**

Same pattern — add `public Log $log;` property and Log construction with `->by(auth()->user())` in the constructor.

- [ ] **Step 3: Add Log to PaymentConfirmedMail**

Same pattern.

- [ ] **Step 4: Add Log to UserPayPaymentMail**

Same pattern.

- [ ] **Step 5: Add Log to NewDiscussionTopicMail**

Same pattern.

- [ ] **Step 6: Commit**

```bash
git add app/Mail/Templates/
git commit -m "feat: add email logging to previously unlogged mail templates"
```

---

## Task 4: Add performance indexes to activity_log table

**Files:**
- Create: `database/migrations/2026_06_11_000000_add_indexes_to_activity_log_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('causer_type');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['log_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['causer_type']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['log_name', 'created_at']);
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_11_000000_add_indexes_to_activity_log_table.php
git commit -m "feat: add performance indexes to activity_log for mail audit queries"
```

---

## Task 5: Create Filament Mail Log page

**Files:**
- Create: `app/Panel/ScheduledConference/Pages/MailLog.php`
- Create: `resources/views/panel/scheduledConference/pages/mail-log.blade.php`

- [ ] **Step 1: Create the Blade view**

```blade
<x-filament-panels::page>
    @livewire('filament-tables::table', ['table' => $this->getTable()])
</x-filament-panels::page>
```

- [ ] **Step 2: Create the Filament page**

```php
<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Resources\Tables\Table;
use Filament\Tables;
use Filament\Tables\Table as TableBuilder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class MailLog extends Page
{
    protected static string $view = 'panel.scheduledConference.pages.mail-log';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Mail Log';

    protected static ?string $title = 'Email Activity Log';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('update', app()->getCurrentScheduledConference());
    }

    public function getTable(): TableBuilder
    {
        return TableBuilder::make()
            ->query(
                Activity::query()
                    ->where('log_name', 'email')
                    ->with(['causer', 'subject'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent At')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Email Type')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('causer.full_name')
                    ->label('Sent By')
                    ->searchable()
                    ->placeholder('System / Auto'),
                Tables\Columns\TextColumn::make('causer.email')
                    ->label('Sender Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject.title')
                    ->label('Subject')
                    ->getStateUsing(function (Activity $record) {
                        $subject = $record->subject;
                        if (!$subject) return '-';

                        if (method_exists($subject, 'getMeta')) {
                            return $subject->getMeta('title') ?? '-';
                        }
                        if (property_exists($subject, 'title')) {
                            return $subject->title;
                        }
                        if (method_exists($subject, 'getKey')) {
                            return $subject->getMorphClass() . ' #' . $subject->getKey();
                        }

                        return '-';
                    })
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject Type')
                    ->badge()
                    ->state(fn (Activity $record) => class_basename($record->subject_type ?? '-'))
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Sent By')
                    ->options(fn () => User::query()
                        ->whereIn('id', Activity::where('log_name', 'email')
                            ->whereNotNull('causer_id')
                            ->distinct()
                            ->pluck('causer_id'))
                        ->pluck('full_name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->where('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->where('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form([
                        Tables\Components\Layout\Panel::make([
                            Tables\Components\TextEntry::make('created_at')
                                ->label('Sent At')
                                ->dateTime('Y-m-d H:i:s'),
                            Tables\Components\TextEntry::make('description')
                                ->label('Email Type'),
                            Tables\Components\TextEntry::make('causer.full_name')
                                ->label('Sent By')
                                ->placeholder('System / Auto'),
                            Tables\Components\TextEntry::make('causer.email')
                                ->label('Sender Email')
                                ->placeholder('-'),
                            Tables\Components\TextEntry::make('subject_type')
                                ->label('Subject Type')
                                ->state(fn (Activity $record) => class_basename($record->subject_type ?? '-')),
                            Tables\Components\TextEntry::make('subject.id')
                                ->label('Subject ID'),
                            Tables\Components\TextEntry::make('properties')
                                ->label('Properties')
                                ->jsonViewer(),
                        ]),
                    ]),
            ])
            ->paginated([10, 25, 50, 100]);
    }
}
```

- [ ] **Step 3: Verify page registers in navigation**

Run: `php artisan route:list --path=mail-log`
Expected: Route exists under the scheduled conference panel

- [ ] **Step 4: Commit**

```bash
git add app/Panel/ScheduledConference/Pages/MailLog.php resources/views/panel/scheduledConference/pages/mail-log.blade.php
git commit -m "feat: add Mail Log page for email audit trail"
```

---

## Task 6: Run full test suite and lint

- [ ] **Step 1: Run tests**

Run: `php artisan test`
Expected: All tests pass

- [ ] **Step 2: Run lint**

Run: `./vendor/bin/pint --test`
Expected: No style violations

- [ ] **Step 3: Fix any issues**

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore: fix lint issues for mail log feature"
```

---

## Verification

After implementation, verify the feature works:

1. **Log creation**: Send an invoice email from the admin panel → check `activity_log` table for a new row with `log_name = 'email'`, correct `causer_id`, and `subject_type` pointing to the Submission
2. **Bulk send**: Use bulk "Send Email" on SubmissionPaymentTable → verify each email gets a log entry with the correct causer
3. **Automatic trigger**: When `SubmissionBillingNotifier` auto-sends → verify log shows `causer_id = NULL` (system-triggered)
4. **Mail Log page**: Navigate to the Mail Log page → verify it shows all email logs with filtering by sender, date range, and email type
5. **Queue safety**: If emails are queued, verify the causer is still captured correctly after queue processing
