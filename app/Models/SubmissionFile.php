<?php

namespace App\Models;

use App\Constants\SubmissionFileCategory;
use App\Notifications\SubmissionFileUploaded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'review_round_id',
        'submission_file_type_id',
        'media_id',
        'user_id',
        'category',
    ];

    protected $with = ['type'];

    public static function booted()
    {
        static::creating(function (SubmissionFile $record) {
            $record->user_id ??= auth()->id();
        });

        static::created(function (SubmissionFile $createdModel) {
            // Send notification when a revision is uploaded, or when an author uploads a review file.
            // Should we created an event for this ?
            // for example SubmissionFilesUploaded, then we can listen to this event and send notification
            $shouldSendNotification = in_array(
                $createdModel->category,
                [
                    SubmissionFileCategory::REVISION_FILES,
                ]
            ) || $createdModel->isAuthorUploadedReviewFile();

            if ($shouldSendNotification) {
                $editors = $createdModel->submission->getEditors();

                if ($editors->count()) {
                    $editors->each(function (User $editor) use ($createdModel) {
                        $editor->notify(new SubmissionFileUploaded($createdModel));
                    });
                }
            }
        });

        static::deleting(function (SubmissionFile $record) {
            if ($record->category == SubmissionFileCategory::REVIEW_FILES) {
                $record->reviewerAssginedFiles()->delete();
            }

            if ($record->category == SubmissionFileCategory::GALLEY_FILES) {
                $record->submissionGalley()->delete();
            }
        });

        static::deleted(function (SubmissionFile $deletedModel) {
            if ($deletedModel->media()->exists()) {
                $deletedModel->media()->delete();
            }
        });
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewRound()
    {
        return $this->belongsTo(SubmissionReviewRound::class, 'review_round_id');
    }

    public function type()
    {
        return $this->belongsTo(SubmissionFileType::class, 'submission_file_type_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isAuthorUploadedReviewFile(): bool
    {
        if ($this->category !== SubmissionFileCategory::REVIEW_FILES) {
            return false;
        }

        if (! $this->user_id) {
            return false;
        }

        $user = $this->user;

        if (! $user) {
            return false;
        }

        return $this->submission->isAuthor($user)
            || $this->submission->isParticipantAuthor($user);
    }

    public function reviewerAssginedFiles(): HasMany
    {
        return $this->hasMany(ReviewerAssignedFile::class);
    }

    public function submissionGalley()
    {
        return $this->hasOne(SubmissionGalley::class);
    }
}
