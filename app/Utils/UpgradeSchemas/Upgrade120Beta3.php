<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Conference;
use App\Models\Discussion;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\SubmissionParticipant;
use Illuminate\Support\Facades\DB;

class Upgrade120Beta2 extends UpgradeBase
{
    public function run(): void
    {
        try {
            DB::beginTransaction();

            Conference::withoutGlobalScopes()
                ->with(['scheduledConferences'])
                ->lazy()
                ->each(function ($conference) {
                    $conferenceAuthorRole = Role::withoutGlobalScopes()
                        ->with(['users'])
                        ->where('conference_id', $conference->getKey())
                        ->where('scheduled_conference_id', 0)
                        ->where('name', UserRole::Author)
                        ->first();

                    $conferenceReviewerRole = Role::withoutGlobalScopes()
                        ->with(['users'])
                        ->where('conference_id', $conference->getKey())
                        ->where('scheduled_conference_id', 0)
                        ->where('name', UserRole::Reviewer)
                        ->first();

                    $conferenceReaderRole = Role::withoutGlobalScopes()
                        ->with(['users'])
                        ->where('conference_id', $conference->getKey())
                        ->where('scheduled_conference_id', 0)
                        ->where('name', UserRole::Reader)
                        ->first();
                        
                    foreach ($conference->scheduledConferences as $scheduledConference) {
                        if ($conferenceAuthorRole){
                            $scheduledConferenceAuthor = Role::withoutGlobalScopes()->firstOrCreate([
                                'name' => UserRole::Author,
                                'conference_id' => $scheduledConference->conference_id,
                                'scheduled_conference_id' => $scheduledConference->getKey(),
                            ]);
    
                            $conferenceAuthorRole->users->each->assignRole($scheduledConferenceAuthor);
    
                            SubmissionParticipant::query()
                                ->where('role_id', $conferenceAuthorRole->getKey())
                                ->whereIn('submission_id', $scheduledConference->submissions()->pluck('id'))
                                ->update([
                                    'role_id' => $scheduledConferenceAuthor->getKey(),
                                ]);
                        }

                        if($conferenceReviewerRole){
                            $scheduledConferenceReviewer = Role::withoutGlobalScopes()->firstOrCreate([
                                'name' => UserRole::Reviewer,
                                'conference_id' => $scheduledConference->conference_id,
                                'scheduled_conference_id' => $scheduledConference->getKey(),
                            ]);

                            $conferenceReviewerRole->users->each->assignRole($scheduledConferenceReviewer);
                        }
                        
                        if($conferenceReaderRole){
                            $scheduledConferenceReader = Role::withoutGlobalScopes()->firstOrCreate([
                                'name' => UserRole::Reader,
                                'conference_id' => $scheduledConference->conference_id,
                                'scheduled_conference_id' => $scheduledConference->getKey(),
                            ]);

                            $conferenceReaderRole->users->each->assignRole($scheduledConferenceReader);
                        }


                    }



                    $conferenceAuthorRole->delete();
                    $conferenceReviewerRole->delete();
                    $conferenceReaderRole->delete();
                });

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }
    }
}
