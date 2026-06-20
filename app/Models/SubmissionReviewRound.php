<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionReviewRound extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'Open';

    public const STATUS_CLOSED = 'Closed';

    protected $fillable = [
        'submission_id',
        'round_number',
        'name',
        'status',
        'triggered_by',
        'default_file_ids',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'default_file_ids' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'review_round_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
