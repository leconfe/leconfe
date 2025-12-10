<?php

namespace App\Models\Enums;

use App\Models\Enums\Concern\UsefulEnums;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    use UsefulEnums;

    case Admin = 'Admin';
    case ConferenceManager = 'Conference Manager';
    case ScheduledConferenceEditor = 'Scheduled Conference Editor';
    case TrackEditor = 'Track Editor';
    case Reviewer = 'Reviewer';
    case Author = 'Author';
    case Reader = 'Reader';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public static function selfAssignedRoles(): array
    {
        return [
            self::Reader,
            self::Author,
            self::Reviewer,
        ];
    }

    public static function selfAssignedRoleNames(): array
    {
        return array_column(self::selfAssignedRoles(), 'name', 'value');
    }

    public static function selfAssignedRoleValues(): array
    {
        return array_column(self::selfAssignedRoles(), 'value', 'name');
    }

    public static function conferenceRoles(): array
    {
        return [
            self::ConferenceManager,
            self::Reader,
        ];
    }

    public static function scheduledConferenceRoles(): array
    {
        return [
            self::ScheduledConferenceEditor,
            self::TrackEditor,
            self::Reviewer,
            self::Author,
        ];
    }

    public static function internalRoles(): array
    {
        return [
            self::Admin,
            self::ConferenceManager,
            self::ScheduledConferenceEditor,
        ];
    }

    public static function getAllowedSelfAssignRoleNames(): array
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        $setting = $scheduledConference?->getMeta('allowed_self_assign_roles') ?? [];

        return collect(self::selfAssignedRoleNames())
            ->filter(fn ($role) => in_array($role, $setting))
            ->toArray();
    }
}
