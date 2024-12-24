<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions;

use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;

class Editing extends \Livewire\Component
{
    public Submission $submission;

    protected $listeners = [
        'refreshSubmission' => '$refresh',
    ];

    public function render()
    {
        if($this->submission->status->isBefore(SubmissionStatus::Editing)){
            return view('panel.scheduledConference.livewire.submissions.stage-not-initiated',);
        }

        return view('panel.scheduledConference.livewire.submissions.editing');
    }
}
