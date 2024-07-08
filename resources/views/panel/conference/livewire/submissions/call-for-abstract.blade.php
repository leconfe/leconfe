@use('App\Panel\Conference\Livewire\Submissions\Components')
@use('App\Models\Enums\SubmissionStage')
@use('App\Constants\SubmissionFileCategory')
@use('App\Models\Enums\SubmissionStatus')

@php
    $user = auth()->user();
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-8 space-y-4">
            @livewire(Components\Files\AbstractFiles::class, ['submission' => $submission, 'category' => SubmissionFileCategory::SUPPLEMENTARY_FILES])

            @livewire(Components\Discussions\DiscussionTopic::class, ['submission' => $submission, 'stage' => SubmissionStage::CallforAbstract, 'lazy' => true])
        </div>
        <div class="sticky z-30 flex flex-col self-start col-span-4 gap-3 top-24" x-data="{ decision:@js($submissionDecision) }">
            @if ($submission->stage == SubmissionStage::PeerReview && !$reviewStageOpen)
                <div class="p-4 text-base text-white rounded-lg bg-primary-700">
                    {{ __('translation.submissions.callforabstractThisSubmission') }}
                </div>
            @endif

            @if($submission->getEditors()->isEmpty() && ! $user->hasRole(\App\Models\Enums\UserRole::Editor->value))
                <div class="px-4 py-3.5 text-base text-white rounded-lg border-2 border-primary-700 bg-primary-500">
                    {{ $user->can('assignParticipant', $submission) ? __('translation.submissions.callforabstractAssignAnEditor') :__('translation.submissions.callforabstractNoEditorAssigned') }}
                </div>
            @else

                @if($submissionDecision)
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 space-y-3 py-5 px-6">
                        <div class="text-base">
                            {{ $submission->status == SubmissionStatus::Declined ? 'Submission Declined' : 'Submission Accepted' }}
                        </div>
                        <button class="text-sm text-primary-500 underline"
                            @@click="decision = !decision" x-text="decision ? 'Change Decision' : 'Cancel'"
                        ></button>
                    </div>
                @endif

                <div @class([
                    'space-y-4',
                    'hidden' => in_array($submission->status, [SubmissionStatus::Published])
                ]) x-show="!decision">
                    @if ($user->can('acceptAbstract', $submission) && ! in_array($this->submission->status, [SubmissionStatus::OnReview, SubmissionStatus::Editing]))
                        {{ $this->acceptAction() }}
                    @endif
                    @if ($user->can('declineAbstract', $submission) && ! in_array($this->submission->status, [SubmissionStatus::Declined]))
                        {{ $this->declineAction() }}
                    @endif
                </div>
            @endif

            @livewire(Components\ParticipantList::class, ['submission' => $submission])
        </div>
    </div>
    <x-filament-actions::modals />
</div>
