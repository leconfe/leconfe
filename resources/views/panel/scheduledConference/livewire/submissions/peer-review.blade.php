@use('App\Models\Enums\SubmissionStage')
@use('App\Models\Enums\SubmissionStatus')
@use('App\Panel\ScheduledConference\Livewire\Submissions\Components')
@use('App\Models\Enums\UserRole')

@php
    $user = auth()->user();
@endphp

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        @if($this->reviewRounds->isNotEmpty())
            <x-filament::tabs :contained="true" class="peer-review-round-tabs !mx-0">
                @foreach($this->reviewRounds as $round)
                    @php
                        $activeRound = $this->selectedRoundId === $round->getKey();
                    @endphp

                    <div
                        role="presentation"
                        @class([
                            'peer-review-round-tab fi-tabs-item group/tab flex items-center gap-x-1 whitespace-nowrap rounded-lg px-1 py-1 text-sm font-medium outline-none transition duration-75',
                            'fi-active fi-tabs-item-active bg-gray-50 dark:bg-white/5' => $activeRound,
                            'hover:bg-gray-50 focus-within:bg-gray-50 dark:hover:bg-white/5 dark:focus-within:bg-white/5' => ! $activeRound,
                        ])
                    >
                        <button
                            type="button"
                            role="tab"
                            @if($activeRound) aria-selected="true" @endif
                            wire:click="selectRound({{ $round->getKey() }})"
                            wire:loading.attr="disabled"
                            wire:target="selectRound"
                            class="flex min-w-0 items-center justify-center rounded-md px-2 py-1 outline-none transition duration-75"
                        >
                            <span @class([
                                'fi-tabs-item-label inline-flex items-center gap-2 transition duration-75',
                                'text-primary-600 dark:text-primary-400' => $activeRound,
                                'text-gray-500 group-hover/tab:text-gray-700 group-focus-visible/tab:text-gray-700 dark:text-gray-400 dark:group-hover/tab:text-gray-200 dark:group-focus-visible/tab:text-gray-200' => ! $activeRound,
                            ])>
                                <span>{{ filled($round->name) ? $round->name : __('general.round').' '.$round->round_number }}</span>
                            </span>
                        </button>

                        @if($activeRound)
                            @can('assignReviewer', $submission)
                                <button
                                    type="button"
                                    class="peer-review-round-tab-edit fi-tabs-item-icon inline-flex h-6 w-6 items-center justify-center rounded-md outline-none transition duration-75 hover:bg-white/10 focus-visible:bg-white/10 focus-visible:ring-2 focus-visible:ring-white/70"
                                    wire:click="mountAction('renameReviewRoundAction', { round: {{ $round->getKey() }} })"
                                    wire:loading.attr="disabled"
                                    wire:target="mountAction('renameReviewRoundAction', { round: {{ $round->getKey() }} })"
                                    x-tooltip.raw.duration.0="{{ __('general.rename_review_round') }}"
                                    aria-label="{{ __('general.rename_review_round') }}"
                                >
                                    <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                                </button>
                            @endcan
                        @endif
                    </div>
                @endforeach
            </x-filament::tabs>
        @endif

        <div class="relative">
            <div
                wire:loading.delay.flex
                wire:target="selectRound"
                role="status"
                aria-live="polite"
                class="peer-review-round-switch-loading pointer-events-none absolute inset-x-0 top-4 z-10 hidden justify-center px-4"
            >
                <div class="inline-flex items-center gap-2 rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm font-medium text-primary-600 shadow-lg ring-1 ring-gray-950/5 dark:border-primary-500/30 dark:bg-gray-900 dark:text-primary-400 dark:ring-white/10">
                    <x-filament::loading-indicator class="h-4 w-4" />
                    <span>{{ __('general.loading') }}</span>
                </div>
            </div>

            <div
                wire:loading.class.delay="opacity-50 pointer-events-none"
                wire:target="selectRound"
                class="peer-review-round-switch-content transition duration-150"
            >
                <div class="grid gap-0 lg:grid-cols-12">
                    <div @class([
                        'space-y-0',
                        'lg:col-span-8' => $user->can('actAsEditor', $submission),
                        'lg:col-span-12' => ! $user->can('actAsEditor', $submission),
                    ])>
                        <div class="p-4">
                            {{-- Review Files --}}
                            @livewire(Components\Files\ReviewFiles::class, ['submission' => $submission])
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
                            $selectedRoundActionable = $this->isSelectedRoundActionable();
                            $showDecisionPanel = $selectedRoundActionable || ($submissionDecision && $this->isSelectedRoundDecisionContext());
                        @endphp

                        <div class="border-t border-gray-200 p-4 lg:col-span-4 lg:border-l lg:border-t-0" x-data="{ decision: @js($submissionDecision) }">
                            @if($submission->stage != SubmissionStage::CallforAbstract)
                                <div class="space-y-4">
                                    @if($selectedRoundActionable && $user->can('assignReviewer', $submission))
                                        <div class="pb-4 border-b border-gray-200">
                                            {{ $this->startNextReviewRoundAction() }}
                                        </div>
                                    @endif

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
        </div>
    </div>

    <x-filament-actions::modals />
</div>
