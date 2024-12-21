<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Review extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Metable;

    public const MODE_DOUBLE_ANONYMOUS = 1;

    public const MODE_ANONYMOUS = 2;

    public const MODE_OPEN = 3;

    protected $casts = [
        'date_assigned' => 'datetime',
        'date_confirmed' => 'datetime',
        'date_completed' => 'datetime',
    ];

    protected $fillable = [
        'submission_id',
        'user_id',
        'status',
        'recommendation',
        'date_assigned',
        'date_confirmed',
        'date_completed',
        'quality',
        'review_author_editor',
        'review_editor',
    ];

    protected static function booted(): void
    {
        static::saving(function (Model $record) {
            if ($record->recommendation) {
                $record->date_completed = now();
            }
        });
    }

    public function reviewSubmitted(): bool
    {
        return ! is_null($this->recommendation) && ! is_null($this->date_completed);
    }

    public function assignedFiles()
    {
        return $this->hasMany(ReviewerAssignedFile::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // public function scopeBySubmission($query, int $submissionId)
    // {
    //     return $query->where('submission_id', $submissionId);
    // }

    public function scopeUser($query, User $user)
    {
        return $query->where('user_id', $user->getKey());
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function confirmed(): bool
    {
        return ! $this->needConfirmation();
    }

    public function needConfirmation(): bool
    {
        return is_null($this->date_confirmed);
    }

    public static function getModeOptions(): array
    {
        return [
            self::MODE_DOUBLE_ANONYMOUS => __('general.anonymous_author'),
            self::MODE_ANONYMOUS => __('general.anonymous_disclosed_author'),
            self::MODE_OPEN => __('general.open'),
        ];
    }

    protected function reviewMode(): Attribute
    {
        return Attribute::make(
            get: fn () => match((int)$this->getMeta('review_mode')){
                self::MODE_DOUBLE_ANONYMOUS => __('general.anonymous_author'),
                self::MODE_ANONYMOUS => __('general.anonymous_disclosed_author'),
                self::MODE_OPEN => __('general.open'),
            },
        );
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'review_mode' => Review::MODE_DOUBLE_ANONYMOUS,
        ];
    }
}
