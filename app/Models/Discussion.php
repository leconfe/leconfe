<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Discussion extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'submission_id',
        'user_id',
        'discussion_topic_id',
        'message',
    ];

    protected static function booted()
    {
        static::creating(function ($discussion) {
            $discussion->sent_by = Auth::id();
        });
    }

    public function topic()
    {
        return $this->belongsTo(DiscussionTopic::class, 'discussion_topic_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}
