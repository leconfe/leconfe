<?php

namespace App\Models;

use App\Models\Concerns\BelongsToConference;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\Enums\ScheduledConferenceType;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
    ];

    protected $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
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
        static::updating(function (ScheduledConference $scheduledConference) {
            if ($scheduledConference->isDirty('state') && $scheduledConference->state == ScheduledConferenceState::Current) {
                static::query()
                    ->where('conference_id', $scheduledConference->conference_id)
                    ->where('state', ScheduledConferenceState::Current->value)
                    ->where('id', '!=', $scheduledConference->id)
                    ->update(['state' => ScheduledConferenceState::Archived]);
            }
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
            'review_invitation_response_deadline' => 30,
            'review_completion_deadline' => 30,
            'timezone' => 'UTC',
            'theme' => 'DefaultTheme',
            'allowed_self_assign_roles' => ['Author', 'Reader'],
            'allow_registration' => true,
            'default_register_country' => 'id',
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
        if (! $this->getMeta('submission_payment')) {
            return false;
        }

        return $this->getMeta('submission_payment');
    }

    public function isCurrent(): bool
    {
        return $this->state == ScheduledConferenceState::Current;
    }

    public function isDraft(): bool
    {
        return $this->state == ScheduledConferenceState::Draft;
    }

    public function isPublished(): bool
    {
        return $this->state == ScheduledConferenceState::Published;
    }

    public function isUpcoming(): bool
    {
        return $this->isPublished();
    }

    public function isArchived(): bool
    {
        return $this->state == ScheduledConferenceState::Archived;
    }

    public function scopeType($query, ScheduledConferenceType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeState($query, ScheduledConferenceState $state)
    {
        return $query->where('state', $state);
    }
}
