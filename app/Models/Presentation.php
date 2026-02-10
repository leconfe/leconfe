<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Enums\PresentationType;
use App\Panel\ScheduledConference\Pages\PresentationDetail;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Presentation extends Model implements HasMedia
{
    use HasFactory, BelongsToScheduledConference, InteractsWithMedia, Metable, Cachable;

    protected $casts = [
        'type' => PresentationType::class,
        'is_final' => 'boolean',
    ];

    protected $fillable = [
        'type',
        'is_final',
    ];

    protected static function booted(): void
    {
        static::created(function (Presentation $presentation) {
            // set as final when presentation is only one exists
            if (!$presentation->is_final && $presentation->submission->presentations()->count() === 1) {
                $presentation->is_final = true;
                $presentation->save();
            }

            $presentation->fetchThumbnailAutomatically();
        });
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('thumbnail')
            ->singleFile();
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PresentationComment::class);
    }

    public function latestComments(): HasMany
    {
        return $this->comments()->latest();
    }

    public function views(): HasMany
    {
        return $this->hasMany(PresentationView::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PresentationLike::class);
    }

    public function registerView(int $userId): bool
    {
        $view = $this->views()->firstOrCreate([
            'user_id' => $userId,
        ]);

        if (! $view->wasRecentlyCreated) {
            return false;
        }

        $this->setMeta('views_count', $this->views()->count());

        return true;
    }

    public function toggleLike(int $userId): bool
    {
        $like = $this->likes()->where('user_id', $userId)->first();

        if ($like) {
            $like->delete();
            $this->setMeta('likes_count', $this->likes()->count());

            return false;
        }

        $this->likes()->create([
            'user_id' => $userId,
        ]);

        $this->setMeta('likes_count', $this->likes()->count());

        return true;
    }

    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function viewsCountMeta(): int
    {
        return (int) $this->getMeta('views_count', 0);
    }

    public function likesCountMeta(): int
    {
        return (int) $this->getMeta('likes_count', 0);
    }

    public function scopeIsFinal(Builder $query, $isFinal = true)
    {
        $query->where('is_final', $isFinal);
    }

    public function setAsFinal(): void
    {
        $this->submission->presentations()->update(['is_final' => false]);
        $this->is_final = true;
        $this->save();
    }

    public function fetchThumbnailAutomatically(bool $force = false): void
    {
        if ($this->getFirstMedia('thumbnail') && !$force) {
            return;
        }

        if ($this->type === PresentationType::Youtube) {
            $this->fetchThumbnailFromYoutube();
        }
    }

    public function fetchThumbnailFromYoutube()
    {
        if ($this->type !== PresentationType::Youtube) {
            return;
        }

        $youtubeVideoId = $this->getMeta('youtube_video_id');
        if (!$youtubeVideoId) {
            return;
        }

        $thumbnailUrl = "https://img.youtube.com/vi/{$youtubeVideoId}/maxresdefault.jpg";

        $this
            ->addMediaCollection('thumbnail')
            ->singleFile();

        $this->addMediaFromUrl($thumbnailUrl)
            ->toMediaCollection('thumbnail');
    }

    public function getIframeUrl()
    {
        return match ($this->type) {
            PresentationType::Youtube => $this->getIframeUrlYoutube(),
            PresentationType::GoogleSlide => $this->getIframeUrlGoogleSlide(),
            default => '',
        };
    }

    public function getIframeUrlYoutube() : string
    {
        return 'https://www.youtube-nocookie.com/embed/' . $this->getMeta('youtube_video_id');
    }

    public function getIframeUrlGoogleSlide() : string
    {
        return $this->getMeta('google_slide_url');
    }

    public function url()
    {
        return PresentationDetail::getUrl(['record' => $this->getKey()]);
    }
}
