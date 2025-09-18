<?php

namespace App\Models;

use App\Models\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'conference_id',
        'scheduled_conference_id',
        'guard_name',
    ];

    public static array $defaultPermissions = [];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('conferences', function (Builder $builder) {

            $conferenceScopeColumn = config('permission.table_names.roles', 'roles').'.conference_id';
            $scheduledConferenceScopeColumn = config('permission.table_names.roles', 'roles').'.scheduled_conference_id';

            $conferenceId = app()->getCurrentConferenceId();
            $builder->where($conferenceScopeColumn, 0);
            if ($conferenceId) {
                $builder->orWhere($conferenceScopeColumn, app()->getCurrentConferenceId());
            }

            $scheduledConferenceId = app()->getCurrentScheduledConferenceId();
            $builder->where($scheduledConferenceScopeColumn, 0);
            if ($scheduledConferenceId) {
                $builder->orWhere($scheduledConferenceScopeColumn, app()->getCurrentScheduledConferenceId());
            }
        });
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function scheduledConference(): BelongsTo
    {
        return $this->belongsTo(ScheduledConference::class);
    }

    public static function getDefaultPermissionsAttribute(): array
    {
        if (empty(static::$defaultPermissions)) {
            static::$defaultPermissions = [
                UserRole::Admin->value => [],
                UserRole::ConferenceManager->value => [
                    'Announcement:create',
                    'Announcement:delete',
                    'Announcement:update',
                    'Announcement:viewAny',
                    'Committee:create',
                    'Committee:delete',
                    'Committee:update',
                    'Committee:viewAny',
                    'Conference:create',
                    'Conference:delete',
                    'Conference:update',
                    'Conference:view',
                    'Discussion:delete',
                    'DiscussionTopic:create',
                    'DiscussionTopic:delete',
                    'DiscussionTopic:close',
                    'DiscussionTopic:update',
                    'Payment:view',
                    'Payment:viewAny',
                    'Payment:create',
                    'Payment:update',
                    'Payment:delete',
                    'Plugin:viewAny',
                    'Plugin:update',
                    'Proceeding:create',
                    'Proceeding:delete',
                    'Proceeding:update',
                    'Proceeding:view',
                    'Proceeding:viewAny',
                    'Role:create',
                    'Role:delete',
                    'Role:update',
                    'Role:view',
                    'Role:viewAny',
                    'ScheduledConference:create',
                    'ScheduledConference:delete',
                    'ScheduledConference:update',
                    'ScheduledConference:viewAny',
                    'Speaker:create',
                    'Speaker:delete',
                    'Speaker:update',
                    'Speaker:viewAny',
                    'StaticPage:create',
                    'StaticPage:delete',
                    'StaticPage:update',
                    'StaticPage:viewAny',
                    'Submission:submitAs',
                    'Submission:acceptPaper',
                    'Submission:approvePayment',
                    'Submission:assignParticipant',
                    'Submission:declinePaper',
                    'Submission:declinePayment',
                    'Submission:delete',
                    'Submission:editing',
                    'Submission:publish',
                    'Submission:reinstateReviewer',
                    'Submission:requestRevision',
                    'Submission:requestWithdraw',
                    'Submission:review',
                    'Submission:sendToEditing',
                    'Submission:skipReview',
                    'Submission:unpublish',
                    'Submission:update',
                    'Submission:uploadAbstract',
                    'Submission:uploadPaper',
                    'Submission:uploadPresentation',
                    'Submission:uploadRevisionFiles',
                    'Submission:view',
                    'Submission:viewAny',
                    'Submission:withdraw',
                    'Submission:decideRegistration',
                    'Submission:deleteRegistration',
                    'SubmissionParticipant:delete',
                    'SubmissionParticipant:notify',
                    'Timeline:create',
                    'Timeline:delete',
                    'Timeline:update',
                    'Timeline:viewAny',
                    'Timeline:view',
                    'Topic:create',
                    'Topic:delete',
                    'Topic:update',
                    'Topic:view',
                    'User:create',
                    'User:delete',
                    'User:disable',
                    'User:enable',
                    'User:loginAs',
                    'User:sendEmail',
                    'User:update',
                    'User:view',
                    'User:viewAny',
                ],
                UserRole::ScheduledConferenceEditor->value => [
                    'Announcement:create',
                    'Announcement:delete',
                    'Announcement:update',
                    'Announcement:viewAny',
                    'Committee:create',
                    'Committee:delete',
                    'Committee:update',
                    'Committee:viewAny',
                    'Discussion:delete',
                    'DiscussionTopic:create',
                    'DiscussionTopic:delete',
                    'DiscussionTopic:close',
                    'DiscussionTopic:update',
                    'Payment:view',
                    'Payment:viewAny',
                    'Payment:create',
                    'Payment:update',
                    'Payment:delete',
                    'Permission:viewAny',
                    'Proceeding:create',
                    'Proceeding:delete',
                    'Proceeding:update',
                    'Proceeding:view',
                    'Proceeding:viewAny',
                    'ScheduledConference:update',
                    'Speaker:create',
                    'Speaker:delete',
                    'Speaker:update',
                    'Speaker:viewAny',
                    'StaticPage:create',
                    'StaticPage:delete',
                    'StaticPage:update',
                    'StaticPage:viewAny',
                    'Submission:submitAs',
                    'Submission:acceptPaper',
                    'Submission:approvePayment',
                    'Submission:declinePaper',
                    'Submission:declinePayment',
                    'Submission:delete',
                    'Submission:editing',
                    'Submission:preview',
                    'Submission:publish',
                    'Submission:reinstateReviewer',
                    'Submission:requestRevision',
                    'Submission:requestWithdraw',
                    'Submission:review',
                    'Submission:sendToEditing',
                    'Submission:skipReview',
                    'Submission:unpublish',
                    'Submission:update',
                    'Submission:uploadAbstract',
                    'Submission:uploadPaper',
                    'Submission:uploadPresentation',
                    'Submission:uploadRevisionFiles',
                    'Submission:view',
                    'Submission:viewAny',
                    'Submission:withdraw',
                    'Submission:decideRegistration',
                    'Submission:deleteRegistration',
                    'SubmissionParticipant:delete',
                    'SubmissionParticipant:notify',
                    'Timeline:create',
                    'Timeline:delete',
                    'Timeline:update',
                    'Timeline:viewAny',
                    'Timeline:view',
                    'Topic:create',
                    'Topic:delete',
                    'Topic:update',
                    'Topic:view',
                ],
                UserRole::TrackEditor->value => [
                    'Submission:acceptPaper',
                    'Submission:approvePayment',
                    'Submission:declinePaper',
                    'Submission:declinePayment',
                    'Submission:delete',
                    'Submission:editing',
                    'Submission:preview',
                    'Submission:publish',
                    'Submission:reinstateReviewer',
                    'Submission:requestRevision',
                    'Submission:requestWithdraw',
                    'Submission:review',
                    'Submission:sendToEditing',
                    'Submission:skipReview',
                    'Submission:unpublish',
                    'Submission:update',
                    'Submission:uploadAbstract',
                    'Submission:uploadPaper',
                    'Submission:uploadPresentation',
                    'Submission:uploadRevisionFiles',
                    'Submission:viewAny',
                    'Submission:withdraw',
                    'Submission:decideRegistration',
                    'Submission:deleteRegistration',
                    'SubmissionParticipant:delete',
                    'SubmissionParticipant:notify',
                    'DiscussionTopic:create',
                    'DiscussionTopic:update',
                ],
                UserRole::Author->value => [
                    'Submission:requestWithdraw',
                    'Submission:uploadAbstract',
                    'Submission:uploadPaper',
                    'Submission:uploadPresentation',
                    'Submission:uploadRevisionFiles',
                    'Submission:viewAny',
                    'DiscussionTopic:create',
                    'DiscussionTopic:update',
                ],
                UserRole::Reviewer->value => [
                    'Submission:review',
                    'Submission:viewAny',
                    'DiscussionTopic:create',
                    'DiscussionTopic:update',
                ],
                UserRole::Reader->value => [],
            ];
        }

        return static::$defaultPermissions;
    }

    public static function getPermissionsForRole(string $roleName): array
    {
        return static::getDefaultPermissionsAttribute()[$roleName] ?? [];
    }

    public function hasDefaultPermission($permission)
    {
        $permission = $this->filterPermission($permission);

        return in_array($permission->name, static::getPermissionsForRole($this->name));
    }
}
