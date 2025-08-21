@use('App\Models\Enums\SubmissionStage')
@use('App\Models\Enums\SubmissionStatus')
@use('App\Panel\ScheduledConference\Livewire\Submissions\Components')
@use('App\Models\Enums\UserRole')

@php
    $user = auth()->user();
@endphp

<div class="space-y-6">
    <div @class([
        'gap-4',
        'grid grid-cols-12' => $user->can('actAsEditor', $submission),
    ])>
        <div class="col-span-8 space-y-4">
            {{-- Papers --}}
            @livewire(Components\Files\PaperFiles::class, ['submission' => $submission])

            {{-- Reviewer List --}}
            @livewire(Components\ReviewerList::class, ['record' => $submission])

            {{-- Reviews --}}
            {{-- Revision Files --}}
            @livewire(Components\Files\RevisionFiles::class, ['submission' => $submission])

            {{-- Discussions --}}
            @livewire(Components\Discussions\PeerReviewDiscussionTopic::class, ['submission' => $submission, 'stage' => SubmissionStage::PeerReview, 'lazy' => true])
        </div>

        @can('actAsEditor', $submission)
        <div class="flex flex-col self-start col-span-4 gap-3" x-data="{ decision: @js($submissionDecision) }">
            @if($submission->stage != SubmissionStage::CallforAbstract)
                @if ($submission->revision_required)
                    <div class="flex items-center p-4 text-sm border rounded-lg border-warning-400 bg-warning-200 text-warning-600" x-show="!decision" role="alert">
                        <span class="text-base text-center">
                            {{ __('general.revisions_have_been_requested') }}
                        </span>
                    </div>
                @endif

                @if($submission->getEditors()->isEmpty())
                    <div class="px-4 py-3.5 text-base text-white rounded-lg border-2 border-primary-700 bg-primary-500">
                        {{ $user->can('assignParticipant', $submission) ? 'Assign an editor to enable the editorial decisions for this stage.' : 'No editor assigned to this submission.' }}
                    </div>
                @endif

                @if($submissionDecision)
                    <div class="px-6 py-5 space-y-3 overflow-hidden bg-white shadow-sm rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="text-base">
                            @if ($submission->status == SubmissionStatus::Declined)
                                {{ __('general.submission_declined') }}
                            @elseif ($submission->skipped_review)
                                {{ __('general.review_skipped') }}
                            @else
                                {{ __('general.submission_accepted') }}
                            @endif
                        </div>
                        <button class="text-sm underline text-primary-500"
                            @@click="decision = !decision" x-text="decision ? 'Change Decision' : 'Cancel'"
                        ></button>
                    </div>
                @endif
                
                @if(!$submission->getEditors()->isEmpty())
                    <div @class([
                        'flex flex-col gap-4 col-span-4',
                        'hidden' => in_array($submission->status, [
                            SubmissionStatus::Queued, 
                            SubmissionStatus::Published,
                            SubmissionStatus::OnPayment,
                            SubmissionStatus::PaymentDeclined,
                        ]),
                    ]) x-show="!decision">
                        @if ($user->can('requestRevision', $submission))
                            {{ $this->requestRevisionAction() }}
                        @endif
                        @if ($user->can('acceptPaper', $submission) && ($submission->status != SubmissionStatus::Editing || $submission->skipped_review))
                            {{ $this->acceptSubmissionAction() }}
                        @endif
                        @if ($user->can('declinePaper', $submission) && ! in_array($submission->status, [SubmissionStatus::Declined]))
                            {{ $this->declineSubmissionAction() }}
                        @endif
                    </div>
                @endif
            @endif
            @livewire(Components\ParticipantList::class, ['submission' => $submission, 'lazy' => true])
        </div>
        @endcan

    </div>
    <x-filament-actions::modals />
</div>
