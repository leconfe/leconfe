@use('App\Models\Enums\SubmissionStage')
@use('App\Models\Enums\SubmissionStatus')
@use('App\Panel\ScheduledConference\Livewire\Submissions\Components')
@use('App\Models\Enums\UserRole')

@php
    $user = auth()->user();
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        @if($this->reviewRounds->isNotEmpty() || $user->can('assignReviewer', $submission))
            <x-filament::tabs :contained="true" class="peer-review-round-tabs !mx-0">
                @foreach($this->reviewRounds as $round)
                    <x-filament::tabs.item
                        :active="$this->selectedRoundId === $round->getKey()"
                        wire:click="selectRound({{ $round->getKey() }})"
                    >
                        <span class="inline-flex items-center gap-2">
                            <span>{{ __('general.round') }} {{ $round->round_number }}</span>
                            <x-filament::badge
                                size="sm"
                                :color="$round->isOpen() ? 'success' : 'gray'"
                            >
                                {{ $round->status }}
                            </x-filament::badge>
                        </span>
                    </x-filament::tabs.item>
                @endforeach

                @can('assignReviewer', $submission)
                    <x-filament::tabs.item
                        wire:click="mountAction('newReviewRoundAction')"
                        icon="heroicon-o-plus"
                    >
                        {{ __('general.new_review_round') }}
                    </x-filament::tabs.item>
                @endcan
            </x-filament::tabs>
        @endif

        <div class="grid gap-0 lg:grid-cols-12">
            <div @class([
                'space-y-0',
                'lg:col-span-8' => $user->can('actAsEditor', $submission),
                'lg:col-span-12' => ! $user->can('actAsEditor', $submission),
            ])>
                <div class="p-4">
                    {{-- Papers --}}
                    @livewire(Components\Files\PaperFiles::class, ['submission' => $submission])
                </div>

                <div class="border-t border-gray-200 p-4">
                    {{-- Reviewer List --}}
                    @livewire(Components\ReviewerList::class, ['record' => $submission])
                </div>

                <div class="border-t border-gray-200 p-4">
                    {{-- Revision Files --}}
                    @livewire(Components\Files\RevisionFiles::class, ['submission' => $submission])
                </div>

                <div class="border-t border-gray-200 p-4">
                    {{-- Discussions --}}
                    @livewire(Components\Discussions\PeerReviewDiscussionTopic::class, ['submission' => $submission, 'stage' => SubmissionStage::PeerReview, 'lazy' => true])
                </div>
            </div>

            @can('actAsEditor', $submission)
                @php
                    $selectedRoundOpen = (bool) ($this->selectedRound?->isOpen());
                    $showDecisionPanel = $selectedRoundOpen || $submissionDecision;
                @endphp

                <div class="border-t border-gray-200 p-4 lg:col-span-4 lg:border-l lg:border-t-0" x-data="{ decision: @js($submissionDecision) }">
                    @if($submission->stage != SubmissionStage::CallforAbstract)
                        <div class="space-y-4">
                            @if($submission->getEditors()->isEmpty())
                                <div class="px-4 py-3.5 text-base text-white rounded-lg border-2 border-primary-700 bg-primary-500">
                                    {{ $user->can('assignParticipant', $submission) ? __('general.assign_an_editor_to_enable_the_editorial') : __('general.no_editor_assigned_this_submission') }}
                                </div>
                            @endif

                            @if($showDecisionPanel)
                                @if ($submission->revision_required)
                                    <div class="flex items-center p-4 text-sm border rounded-lg border-warning-400 bg-warning-200 text-warning-600" x-show="!decision" role="alert">
                                        <span class="text-base text-center">
                                            {{ __('general.revisions_have_been_requested') }}
                                        </span>
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
                                            @@click="decision = !decision" x-text="decision ? @js(__('general.change_decision')) : @js(__('general.cancel'))"
                                        ></button>
                                    </div>
                                @endif

                                @if(! $submission->getEditors()->isEmpty())
                                    <div @class([
                                        'flex flex-col gap-4',
                                        'hidden' => in_array($submission->status, [
                                            SubmissionStatus::Queued,
                                            SubmissionStatus::Published,
                                            SubmissionStatus::Withdrawn,
                                            SubmissionStatus::OnPayment,
                                            SubmissionStatus::PaymentDeclined,
                                        ]),
                                    ]) x-show="!decision">
                                        @if ($user->can('requestRevision', $submission))
                                            {{ $this->requestRevisionAction() }}
                                        @endif
                                        @if ($user->can('acceptPaper', $submission))
                                            {{ $this->acceptSubmissionAction() }}
                                        @endif
                                        @if ($user->can('declinePaper', $submission))
                                            {{ $this->declineSubmissionAction() }}
                                        @endif
                                    </div>
                                @endif
                            @elseif($this->selectedRound)
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                                    {{ __('general.sent_for_a_new_round_of_reviews') }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 border-t border-gray-200 pt-4">
                        @livewire(Components\ParticipantList::class, ['submission' => $submission, 'lazy' => true])
                    </div>
                </div>
            @endcan
        </div>
    </div>

    <x-filament-actions::modals />
</div>
