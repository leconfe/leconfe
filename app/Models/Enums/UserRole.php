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
    case Participant = 'Participant';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public static function selfAssignedRoles(): array
    {
        return [
            self::Author,
            self::Reviewer,
            self::Participant,
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
        ];
    }

    public static function scheduledConferenceRoles(): array
    {
        return [
            self::ScheduledConferenceEditor,
            self::TrackEditor,
            self::Reviewer,
            self::Author,
            self::Participant,
        ];
    }

    public static function internalRoles(): array
    {
        return [
            self::Admin,
            self::ConferenceManager,
            self::ScheduledConferenceEditor,
            self::TrackEditor,
        ];
    }

    public static function getAllowedSelfAssignRoleNames(): array
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        $setting = $scheduledConference?->getMeta('allowed_self_assign_roles') ?? [];

        return collect(self::selfAssignedRoleNames())->filter(function ($role) use ($setting) {
            if ($role !== self::Reviewer) {
                return true;
            } else {
                return in_array($role, $setting);
            }
        })->toArray();
    }

    public static function getRoleDescriptions(): array
    {
        return [
            'Author' => 'Submit papers and participate in the conference as an author.',
            'Reviewer' => 'Review submitted papers and provide feedback on research quality.',
            'Participant' => 'Attend the conference.',
        ];
    }

    public static function getAllowedSelfAssignRoleDescriptions(): array
    {
        $allowedRoles = self::getAllowedSelfAssignRoleNames();
        $descriptions = self::getRoleDescriptions();

        return collect($allowedRoles)
            ->mapWithKeys(fn(string $roleName, string $key) => [$key => $descriptions[$roleName] ?? ''])
            ->filter()
            ->toArray();
    }
}
