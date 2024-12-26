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
            self::TYPE_REGISTRATION_OPEN => 'Registration Open',
            self::TYPE_REGISTRATION_CLOSE => 'Registration Close',
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

    public static function isRegistrationOpen(): bool
    {
        $timelineRegistrationOpen = self::where('type', self::TYPE_REGISTRATION_OPEN)->first();
        $timelineRegistrationClose = self::where('type', self::TYPE_REGISTRATION_CLOSE)->first();

        if (! $timelineRegistrationOpen) {
            return false;
        }

        if ($timelineRegistrationOpen->date->isPast() && (! $timelineRegistrationClose || $timelineRegistrationClose->date->isFuture())) {
            return true;
        }

        return false;
    }

    protected function timeSpan(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::squish(
                $this->getEarliestTime()->format(Setting::get('format_time')).
                ' - '.
                $this->getLatestTime()->format(Setting::get('format_time'))
            ),
        );
    }

    public function getEarliestTime(): Carbon
    {
        $earliest_session = $this->sessions()
            ->orderBy('start_at', 'ASC')
            ->limit(1)
            ->first();

        if (! $earliest_session) {
            return $this->date;
        }

        return $earliest_session->getStartDate();
    }

    public function getLatestTime(): Carbon
    {
        $latest_session = $this->sessions()
            ->orderBy('end_at', 'DESC')
            ->limit(1)
            ->first();

        if (! $latest_session) {
            return $this->date->endOfDay();
        }

        return $latest_session->getEndDate();
    }

    public function isOngoing(): bool
    {
        return ! $this->getEarliestTime()->isFuture() && ! $this->getLatestTime()->isPast();
    }

    public function isRequireAttendance(): bool
    {
        return $this->require_attendance;
    }

    public function canShown(): bool
    {
        if (! $this->isRequireAttendance()) {
            return false;
        }

        if ($this->hide) {
            return false;
        }

        return true;
    }

    public function canAttend(): bool
    {
        if (! $this->canShown()) {
            return false;
        }

        if (! $this->isOngoing()) {
            return false;
        }

        return true;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }
}
