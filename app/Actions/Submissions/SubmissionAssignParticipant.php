<?php

namespace App\Actions\Submissions;

use App\Classes\Log;
use App\Mail\Templates\ParticipantAssignedMail;
use App\Models\MailTemplate;
use App\Models\Submission;
use App\Notifications\ParticipantAssigned;
use App\Support\Multilingual\RecipientLocale;
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
                $mail = new ParticipantAssignedMail($submissionParticipant);

                // Text written by the sender is sent as written; the rest of the template
                // is taken in the recipient's language.
                if (filled($message) || filled($subject)) {
                    $template = MailTemplate::contentFor(
                        ParticipantAssignedMail::class,
                        RecipientLocale::for($submissionParticipant->user),
                    );

                    $mail->contentUsing(filled($message) ? $message : $template['html_template'])
                        ->subjectUsing(filled($subject) ? $subject : $template['subject']);
                }

                Mail::to($submissionParticipant->user)->send($mail);
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
