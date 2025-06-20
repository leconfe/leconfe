<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Metable\Metable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Review extends Model implements HasMedia
{
    use Cachable, HasFactory, InteractsWithMedia, Metable;

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
        'date_acknowledged',
        'quality',
        'score',
        'review_author_editor',
        'review_editor',
    ];

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

    public function reviewMode(): Attribute
    {
        return Attribute::make(
            get: fn () => match ((int) $this->getMeta('review_mode')) {
                self::MODE_DOUBLE_ANONYMOUS => __('general.anonymous_author'),
                self::MODE_ANONYMOUS => __('general.anonymous_disclosed_author'),
                self::MODE_OPEN => __('general.open'),
            },
        );
    }

    public function isShowAuthor()
    {
        return in_array($this->getMeta('review_mode'), [Review::MODE_ANONYMOUS, Review::MODE_OPEN]);
    }

    public function isOpenReview()
    {
        return $this->getMeta('review_mode') == Review::MODE_OPEN || $this->getMeta('open_review_for_author');
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'review_mode' => Review::MODE_DOUBLE_ANONYMOUS,
        ];
    }

    public function calculateReviewScore(array $data) : float
    {
        $reviewForms = ReviewFormItem::query()  
            ->with(['meta'])
            ->whereIn('id', array_keys($data))
            ->get();

        return collect($data)
            ->filter(fn($item, $key) => $reviewForms->find($key)?->isEnableScoring())
            ->reduce(function(?int $carry, $value, int $key) use ($reviewForms){
                $reviewForm = $reviewForms->find($key); 

                $weight = $reviewForm->weight / 10;

                return $carry + ($value * $weight);
            });
    }
}
