<?php

namespace App\Models;

use App\Facades\Setting;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\LocalizedMetable;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timeline extends Model
{
    use BelongsToScheduledConference, Cachable, HasFactory, LocalizedMetable;

    public const TYPE_SUBMISSION_OPEN = 1;

    public const TYPE_SUBMISSION_CLOSE = 2;

    public const TYPE_REGISTRATION_OPEN = 3;

    public const TYPE_REGISTRATION_CLOSE = 4;

    protected $fillable = [
        'scheduled_conference_id',
        'name',
        'description',
        'date',
        'date_end',
        'type',
        'hide',
        'require_attendance',
    ];

    protected $casts = [
        'date' => 'datetime',
        'date_end' => 'datetime',
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

    protected function fullDate(): Attribute
    {
        return Attribute::make(
            get: function () {
                $formattedDate = $this->date->format(Setting::get('format_date'));

                if ($this->date_end) {
                    $formattedDate .= ' - '.$this->date_end->format(Setting::get('format_date'));
                }

                return $formattedDate;
            },
        );
    }
}
