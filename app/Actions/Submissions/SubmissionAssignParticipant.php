<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Mail\Templates\ParticipantAssignedMail;
use App\Models\DefaultMailTemplate;
use App\Models\Submission;
use App\Notifications\ParticipantAssigned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmissionAssignParticipant
{
    use AsAction;

    public function handle(Submission $submission, $userId, $roleId, $sendNotification = true, $subject = null, $message = null, $by = null): Submission
    {
        try {
            DB::beginTransaction();

            $submissionParticipant = $submission->participants()->create([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);

            Log::make(
                name: 'submission',
                subject: $submission,
                description: __('general.participant_assigned', [
                    'name' => $submissionParticipant->user->fullName,
                    'role' => $submissionParticipant->role->name,
                ]),
                event: 'participant-assigned'
            )
                ->by($by)
                ->properties([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ])->save();

            if ($sendNotification) {
                $mailTemplate = DefaultMailTemplate::where('mailable', ParticipantAssignedMail::class)->first();
                Mail::to($submissionParticipant->user->email)
                    ->send(
                        (new ParticipantAssignedMail($submissionParticipant))
                            ->contentUsing($message ?? $mailTemplate->html_template)
                            ->subjectUsing($subject ?? $mailTemplate->subject)
                    );
            }

            $submissionParticipant->user->notify(
                new ParticipantAssigned($submission)
            );

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $submission;
    }
}
