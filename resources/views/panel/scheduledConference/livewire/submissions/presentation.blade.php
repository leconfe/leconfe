@use('App\Panel\ScheduledConference\Livewire\Submissions\Components')
@use('App\Models\Enums\SubmissionStage')
@use('App\Constants\SubmissionFileCategory')
@use('App\Models\Enums\SubmissionStatus')
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
            @livewire(Components\PresentationList::class, ['submission' => $submission])

            @livewire(Components\Discussions\DiscussionTopic::class, ['submission' => $submission, 'stage' => SubmissionStage::Presentation, 'lazy' => true])
        </div>
        @can('actAsEditor', $submission)
        <div class="flex flex-col self-start col-span-4 gap-3" x-data="{ decision: @js($submissionDecision) }">
                @if ($submission->getEditors()->isEmpty())
                    <div class="px-4 py-3.5 text-base text-white rounded-lg border-2 border-primary-700 bg-primary-500">
                        {{ $user->can('assignParticipant', $submission) ?  __('general.assign_an_editor_to_enable_the_editorial'): __('general.no_editor_assigned_this_submission') }}
                    </div>
                @endif
                
                @if ($submissionDecision)
                    <div
                        class="px-6 py-5 space-y-3 overflow-hidden bg-white shadow-sm rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="text-base">
                            {{ __('general.submission_send_to_editing') }}
                        </div>
                    </div>
                @endif

                <div @class([
                    'flex flex-col gap-4 col-span-4',
                    'hidden' => in_array($submission->status, [
                        SubmissionStatus::Queued,
                        SubmissionStatus::OnPayment,
                        SubmissionStatus::Published,
                        SubmissionStatus::PaymentDeclined,
                    ]),
                ]) x-show="!decision">
                    @if ($user->can('sendToEditing', $submission))
                        {{ $this->sendToEditingAction() }}
                    @endif
                </div>

            @livewire(Components\ParticipantList::class, ['submission' => $submission, 'lazy' => true])
        </div>
        @endcan
    </div>
    <x-filament-actions::modals />
</div>
