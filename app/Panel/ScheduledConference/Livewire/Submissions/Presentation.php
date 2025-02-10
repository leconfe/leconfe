<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions;

use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Presentation extends Component implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    public Submission $submission;

    protected $listeners = [
        'refreshSubmission' => '$refresh',
    ];

    public function sendToEditingAction()
    {
        return Action::make('sendToEditing')
            ->label(__('general.send_to_editing'))
            ->modalHeading(__('general.send_to_editing'))
            ->modalSubmitActionLabel(__('general.send_to_editing'))
            ->authorize('sendToEditing', $this->submission)
            ->modalWidth('2xl')
            ->record($this->submission)
            ->successNotificationTitle(__('general.accepted'))
            ->extraAttributes(['class' => 'w-full'])
            ->requiresConfirmation()
            ->icon('lineawesome-check-circle-solid')
            ->action(
                function (Action $action, array $data) {
                    try {
                        $this->submission->state()->sendToEditing();

                        $action->successRedirectUrl(
                            SubmissionResource::getUrl('view', [
                                'record' => $this->submission->getKey(),
                            ])
                        );

                        $action->success();
                    } catch (\Throwable $th) {
                        Log::error($th->getMessage());
                        $action->failureNotificationTitle(__('general.failed_to_send_to_editing'));
                        $action->failure();
                    }
                }
            );
    }

    public function render()
    {
        if ($this->submission->status->isBefore(SubmissionStatus::OnPresentation)) {
            return view('panel.scheduledConference.livewire.submissions.message', ['message' => 'Stage not initiated']);
        }

        return view('panel.scheduledConference.livewire.submissions.presentation', [
            'submissionDecision' => in_array($this->submission->status, [
                SubmissionStatus::OnReview,
                SubmissionStatus::Editing,
                SubmissionStatus::Declined,
            ]),
        ]);
    }
}
