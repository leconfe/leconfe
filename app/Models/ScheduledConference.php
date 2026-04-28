<?php

namespace App\Models;

use App\Actions\ScheduledConferences\ScheduledConferencePing;
use App\Facades\Setting;
use App\Models\Concerns\BelongsToConference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\Enums\ScheduledConferenceType;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ScheduledConference extends Model implements HasAvatar, HasMedia, HasName
{
    use BelongsToConference, Cachable, HasFactory, InteractsWithMedia, Metable, SoftDeletes;

    protected $fillable = [
        'conference_id',
        'path',
        'title',
        'date_start',
        'date_end',
        'state',
        'type',
        'is_published',
        'featured',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'current' => 'boolean',
        'date_start' => 'date',
        'date_end' => 'date',
        'type' => ScheduledConferenceType::class,
        'state' => ScheduledConferenceState::class,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (ScheduledConference $scheduledConference) {
            Announcement::query()
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            SpeakerRole::query()
                ->with(['speakers'])
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            CommitteeRole::query()
                ->with(['committees'])
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            Submission::query()
                ->with(['submissionFiles', 'authors', 'participants', 'reviews', 'media'])
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            PaymentFee::query()
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            Role::query()
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            NavigationMenu::query()
                ->withoutGlobalScopes()
                ->with(['items'])
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            StakeholderLevel::query()
                ->withoutGlobalScopes()
                ->with(['stakeholders' => fn($query) => $query->withoutGlobalScopes()])
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            Stakeholder::query()
                ->withoutGlobalScopes()
                ->whereNull('level_id')
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();

            Track::query()
                ->withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->lazy()
                ->each
                ->delete();
        });
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'timezone' => 'UTC',
            'submission_payment' => false,
            'before_you_begin' => __('general.before_you_begin_current_scheduled', ['title' => $this->title]),
            'submission_checklist' => __('general.submission_checklist_following_requirements'),
            'review_mode' => Review::MODE_DOUBLE_ANONYMOUS,
            'review_invitation_response_deadline' => 21,
            'review_completion_deadline' => 28,
            'theme' => 'DefaultTheme',
            'allowed_self_assign_roles' => ['Author'],
            'allow_registration' => true,
            'default_register_country' => 'id',
            'default_open_review_for_author' => true,
            'invoice_number' => 1,
            'invoice_enable' => false,
            'receipt_enable' => false,
            'submission_payment' => false,
            'participant_payment' => false,
            'submission_billing_stage' => SubmissionStage::PeerReview->value,
            'payment_opened_at' => null,
            'payment_closed_at' => null,
            'required_given_name' => true,
            'required_family_name' => false,
            'required_public_name' => false,
            'required_affiliation' => false,
            'required_country' => false,
            'required_phone' => false,
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    public function speakerRoles(): HasMany
    {
        return $this->hasMany(SpeakerRole::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function staticPages(): HasMany
    {
        return $this->hasMany(StaticPage::class);
    }

    public function getUrl(): string
    {
        return $this->getHomeUrl();
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }

    public function registration(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function registrationType(): HasMany
    {
        return $this->hasMany(RegistrationType::class);
    }

    public function getPanelUrl(): string
    {
        $currentConference = app()->getCurrentConference() ?? $this->conference;

        return route('filament.scheduledConference.pages.dashboard', ['serie' => $this->path, 'conference' => $currentConference]);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo', 'tenant');
    }

    public function getFilamentName(): string
    {
        return $this->title;
    }

    public function hasThumbnail(): bool
    {
        return $this->getMedia('thumbnail')->isNotEmpty();
    }

    public function getThumbnailUrl(): string
    {
        return $this->getFirstMedia('thumbnail')?->getAvailableUrl(['thumb', 'thumb-xl']) ?? Vite::asset('resources/assets/images/placeholder-vertical.jpg');
    }

    public function getHomeUrl(): string
    {
        return route('livewirePageGroup.scheduledConference.pages.home', ['conference' => $this->conference, 'serie' => $this->path]);
    }

    public function isSubmissionRequirePayment(): bool
    {
        if (!$this->getMeta('submission_payment')) {
            return false;
        }

        return $this->getMeta('submission_payment');
    }

    public function scopeType($query, ScheduledConferenceType $type)
    {
        return $query->where('type', $type);
    }

    public function scopePublished($query, bool $isPublished = true)
    {
        return $query->where('is_published', $isPublished);
    }

    public function scopeFilterByCategories($query, array $categories)
    {
        return $query->whereHas('meta', function ($m) use ($categories) {
            $m->where('key', 'category')
                ->where(function ($q) use ($categories) {
                    foreach ($categories as $category) {
                        $q->orWhereJsonContains('value', $category);
                    }
                });
        });
    }

    public function isInvoiceEnabled(): bool
    {
        return $this->getMeta('invoice_enable');
    }

    public function isReceiptEnabled(): bool
    {
        return $this->isInvoiceEnabled() && $this->getMeta('receipt_enable');
    }

    public function isSubmissionPaymentEnabled(): bool
    {
        return $this->getMeta('submission_payment');
    }

    public function isSubmissionPaymentAutoNotify(): bool
    {
        return $this->getMeta('submission_payment_auto_notify', true);
    }

    public static function getSubmissionBillingStageOptions(): array
    {
        return [
            SubmissionStage::CallforAbstract->value => SubmissionStage::CallforAbstract->value,
            SubmissionStage::PeerReview->value => SubmissionStage::PeerReview->value,
            SubmissionStage::Presentation->value => SubmissionStage::Presentation->value,
            SubmissionStage::Editing->value => SubmissionStage::Editing->value,
        ];
    }

    public function getSubmissionBillingStage(): SubmissionStage
    {
        $stage = SubmissionStage::tryFrom(
            (string) $this->getMeta('submission_billing_stage', SubmissionStage::PeerReview->value)
        );

        if (! in_array($stage, [
            SubmissionStage::CallforAbstract,
            SubmissionStage::PeerReview,
            SubmissionStage::Presentation,
            SubmissionStage::Editing,
        ], true)) {
            return SubmissionStage::PeerReview;
        }

        return $stage;
    }

    public function isParticipantPaymentEnabled(): bool
    {
        return $this->getMeta('participant_payment');
    }

    public function isParticipantRegistrationEnabled(): bool
    {
        return $this->isParticipantPaymentEnabled();
    }

    public function getPaymentOpenedAt(): ?Carbon
    {
        $openedAt = $this->getMeta('payment_opened_at');

        return filled($openedAt) ? Carbon::parse($openedAt)->startOfDay() : null;
    }

    public function getPaymentClosedAt(): ?Carbon
    {
        $closedAt = $this->getMeta('payment_closed_at');

        return filled($closedAt) ? Carbon::parse($closedAt)->endOfDay() : null;
    }

    public function isPaymentOpen(?Carbon $date = null): bool
    {
        $date ??= now();

        if ($openedAt = $this->getPaymentOpenedAt()) {
            if ($date->lt($openedAt)) {
                return false;
            }
        }

        if ($closedAt = $this->getPaymentClosedAt()) {
            if ($date->gt($closedAt)) {
                return false;
            }
        }

        return true;
    }

    public function generateInvoiceNumber(?int $number = null)
    {
        $number ??= $this->getMeta('invoice_number');

        $generatedNumber = $this->getMeta('invoice_prefix_number') . str_pad($number, 3, '0', STR_PAD_LEFT) . $this->getMeta('invoice_suffix_number');

        return $generatedNumber;
    }

    public function getLatestInvoiceNumber(): int
    {
        return $this->getMeta('invoice_number');
    }

    public function updateLatestInvoiceNumber(int $number): void
    {
        $this->setMeta('invoice_number', $number);
    }

    public function getEntityUniqueId(): ?string
    {
        return $this->getMeta('entity_unique_id');
    }

    public function getEntityToken(): ?string
    {
        $token = $this->getMeta('entity_token');
        if (!$token) {
            $this->registerEntity();

            $token = $this->getMeta('entity_token');
        }

        return $token;
    }

    public function registerEntity(): void
    {
        if (!app()->isProduction())
            return;

        $response = Http::acceptJson()->post(app()->getApiUrl('leconfe/auth/register'), [
            'name' => $this->title,
            'url' => $this->getUrl(),
        ]);

        if ($response->failed()) {
            $response->throw();
        }

        $data = $response->json();

        $this->setManyMeta([
            'entity_unique_id' => $data['unique_id'],
            'entity_token' => $data['token'],
        ]);
    }

    public function getContextString(): string
    {
        return 'scheduled-conference';
    }

    protected function fullDate(): Attribute
    {
        return Attribute::make(
            get: function () {
                $start = $this->date_start?->format(Setting::get('format_date'));
                $end = $this->date_end?->format(Setting::get('format_date'));

                if ($start && $end) {
                    return "{$start} - {$end}";
                }

                if ($start) {
                    return $start;
                }

                if ($end) {
                    return $end;
                }

                return '';
            },
        );
    }

    public function ping()
    {
        if (!Cache::has('scheduled_conference_ping_' . $this->getKey())) {
            ScheduledConferencePing::dispatch($this)->onConnection('async');
        }
    }
}
