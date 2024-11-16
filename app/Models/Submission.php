<?php

namespace App\Models;

use App\Frontend\Conference\Pages\Paper;
use App\Models\Concerns\HasDOI;
use App\Models\Concerns\HasTopics;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\States\Submission\BaseSubmissionState;
use App\Models\States\Submission\DeclinedPaymentSubmissionState;
use App\Models\States\Submission\DeclinedSubmissionState;
use App\Models\States\Submission\EditingSubmissionState;
use App\Models\States\Submission\IncompleteSubmissionState;
use App\Models\States\Submission\OnPaymentSubmissionState;
use App\Models\States\Submission\OnPresentationSubmissionState;
use App\Models\States\Submission\OnReviewSubmissionState;
use App\Models\States\Submission\PublishedSubmissionState;
use App\Models\States\Submission\QueuedSubmissionState;
use App\Models\States\Submission\WithdrawnSubmissionState;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Plank\Metable\Metable;
use Spatie\Activitylog\Models\Activity;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;

class Submission extends Model implements HasMedia, Sortable
{
    use Cachable, HasDOI, HasFactory, HasTags, HasTopics, InteractsWithMedia, Metable, SortableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'proceeding_id',
        'track_id',
        'skipped_review',
        'stage',
        'status',
        'revision_required',
        'withdrawn_reason',
        'withdrawn_at',
        'published_at',
        'proceeding_order_column',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'stage' => SubmissionStage::class,
        'status' => SubmissionStatus::class,
        'published_at' => 'datetime',
        'skipped_review' => 'boolean',
        'revision_required' => 'boolean',
    ];

    public $sortable = [
        'order_column_name' => 'proceeding_order_column',
        'sort_when_creating' => true,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {

        static::creating(function (Submission $submission) {
            $submission->user_id ??= Auth::id();
            $submission->conference_id ??= app()->getCurrentConferenceId();
            $submission->scheduled_conference_id ??= app()->getCurrentScheduledConferenceId();

            if (! $submission->track_id) {
                $submission->track_id = Track::withoutGlobalScopes()->where('scheduled_conference_id', $submission->scheduled_conference_id)->first()?->getKey();
            }
        });

        static::deleting(function (Submission $submission) {
            $submission->submissionFiles->each->delete();
            $submission->authors->each->delete();
            $submission->participants->each->delete();
            $submission->reviews->each->delete();
            $submission->media->each->delete();
        });

        static::created(function (Submission $submission) {
            $submission->participants()->create([
                'user_id' => $submission->user_id,
                'role_id' => Role::withoutGlobalScopes()->where('conference_id', $submission->conference_id)->where('name', UserRole::Author->value)->first()->getKey(),
            ]);
        });
    }

    public function proceeding(): BelongsTo
    {
        return $this->belongsTo(Proceeding::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function assignProceeding(Proceeding|int $proceeding)
    {
        if (is_int($proceeding)) {
            $proceeding = Proceeding::find($proceeding);
        }

        $this->proceeding()->associate($proceeding);
        $this->save();
    }

    public function unassignProceeding()
    {
        $this->status = SubmissionStatus::Editing;
        $this->proceeding()->dissociate();
        $this->save();
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function reviewerAssignedFiles(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function conference()
    {
        return $this->belongsTo(Conference::class);
    }

    public function scheduledConference()
    {
        return $this->belongsTo(ScheduledConference::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submissionFiles()
    {
        return $this->hasMany(SubmissionFile::class);
    }

    public function galleys()
    {
        return $this->hasMany(SubmissionGalley::class);
    }

    public function discussionTopics()
    {
        return $this->hasMany(DiscussionTopic::class);
    }

    public function participants()
    {
        return $this->hasMany(SubmissionParticipant::class);
    }

    public function editors()
    {
        return $this->participants()
            ->whereHas('role', fn (Builder $query) => $query->whereIn('name', [UserRole::ScheduledConferenceEditor, UserRole::TrackEditor]));
    }

    public function isPublishedOnExternal()
    {
        return $this->getMeta('paper_published_on_external', false);
    }

    public function getPublicUrl()
    {
        return $this->isPublishedOnExternal() ? $this->getMeta('paper_external_url') : route('livewirePageGroup.conference.pages.paper', ['submission' => $this->id]);
    }

    public function authors()
    {
        return $this->hasMany(Author::class);
    }

    public function registration(): HasOne
    {
        return $this->hasOne(Registration::class)->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }

    public function isParticipantEditor(User $user): bool
    {
        $isParticipantEditor = $this->editors()
            ->where('user_id', $user->getKey())
            ->limit(1)
            ->first();

        if (! $isParticipantEditor) {
            return false;
        }

        return true;
    }

    public function isParticipantAuthor(User $user): bool
    {
        $isParticipantAuthor = $this->participants()
            ->whereHas('role', function (Builder $query) {
                $query->where('name', UserRole::Author->value);
            })
            ->where('user_id', $user->getKey())
            ->limit(1)
            ->first();

        if (! $isParticipantAuthor) {
            return false;
        }

        return true;
    }

    public function scopePublished(Builder $query)
    {
        return $query->status(SubmissionStatus::Published);
    }

    public function scopeStage(Builder $query, SubmissionStage $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeStatus(Builder $query, SubmissionStatus $status)
    {
        return $query->where('status', $status);
    }

    public function isPublished(): bool
    {
        return $this->status == SubmissionStatus::Published;
    }

    public function isDeclined(): bool
    {
        return $this->status == SubmissionStatus::Declined;
    }

    public function isIncomplete(): bool
    {
        return $this->status == SubmissionStatus::Incomplete;
    }

    /**
     * Get all the editors of this submission
     */
    public function getEditors(): Collection
    {
        $editorIds = $this->editors()
            ->pluck('user_id');

        return User::whereIn('id', $editorIds)->get();
    }

    public function state(): BaseSubmissionState
    {
        return match ($this->status) {
            SubmissionStatus::Incomplete => new IncompleteSubmissionState($this),
            SubmissionStatus::Queued => new QueuedSubmissionState($this),
            SubmissionStatus::OnPayment => new OnPaymentSubmissionState($this),
            SubmissionStatus::OnReview => new OnReviewSubmissionState($this),
            SubmissionStatus::OnPresentation => new OnPresentationSubmissionState($this),
            SubmissionStatus::Editing => new EditingSubmissionState($this),
            SubmissionStatus::Published => new PublishedSubmissionState($this),
            SubmissionStatus::Declined => new DeclinedSubmissionState($this),
            SubmissionStatus::PaymentDeclined => new DeclinedPaymentSubmissionState($this),
            SubmissionStatus::Withdrawn => new WithdrawnSubmissionState($this),
            default => throw new \Exception('Invalid submission status'),
        };
    }

    public function buildSortQuery()
    {
        return static::query()->where('proceeding_order_column', $this->proceeding_id);
    }

    public function getUrl(): string
    {
        return route(Paper::getRouteName('conference'), [
            'submission' => $this,
            'conference' => $this->conference,
        ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->keepOriginalImageFormat()
            ->width(500)
            ->height(500);
    }
}
