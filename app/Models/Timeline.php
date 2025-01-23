<?php

namespace App\Models;

use App\Facades\Setting;
use App\Models\Concerns\BelongsToScheduledConference;
use Carbon\Carbon;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Timeline extends Model
{
    use BelongsToScheduledConference, Cachable, HasFactory;

    public const TYPE_SUBMISSION_OPEN = 1;

    public const TYPE_SUBMISSION_CLOSE = 2;

    public const TYPE_REGISTRATION_OPEN = 3;

    public const TYPE_REGISTRATION_CLOSE = 4;

    protected $fillable = [
        'scheduled_conference_id',
        'name',
        'description',
        'date',
        'type',
        'hide',
        'require_attendance',
    ];

    protected $casts = [
        'date' => 'datetime',
        'hide' => 'boolean',
    ];

    public static function getTypes(): array
    {
        return [
            self::TYPE_SUBMISSION_OPEN => 'Submission Open',
            self::TYPE_SUBMISSION_CLOSE => 'Submission Close',
        ];
    }

    public static function isSubmissionOpen(): bool
    {
        $timelineSubmissionOpen = self::where('type', self::TYPE_SUBMISSION_OPEN)->first();
        $timelineSubmissionClose = self::where('type', self::TYPE_SUBMISSION_CLOSE)->first();

        if (! $timelineSubmissionOpen) {
            return false;
        }

        if ($timelineSubmissionOpen->date->isPast() && (! $timelineSubmissionClose || $timelineSubmissionClose->date->isFuture())) {
            return true;
        }

        return false;
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }
}
