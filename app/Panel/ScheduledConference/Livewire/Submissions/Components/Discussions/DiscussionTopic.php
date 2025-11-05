<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Discussions;

use App\Actions\Submissions\CreateDiscussionTopic;
use App\Actions\Submissions\UpdateDiscussionTopic;
use App\Models\DiscussionTopic as ModelsDiscussionTopic;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\UserRole;
use App\Models\Submission;
use App\Notifications\NewDiscussionTopic;
use Awcodes\FilamentBadgeableColumn\Components\Badge;
use Awcodes\FilamentBadgeableColumn\Components\BadgeableColumn;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\Livewire;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DiscussionTopic extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Submission $submission;

    public SubmissionStage $stage;

    public function mount(Submission $submission, SubmissionStage $stage) {}

    public function getEloquentQuery()
    {
        return ModelsDiscussionTopic::query()
            ->with(['discussions.user'])
            ->where('submission_id', $this->submission->getKey())
            ->where('stage', $this->stage)
            ->when(
                !auth()->user()->can('actAsEditor', $this->submission),
                fn($query) => $query->whereHas('participants', fn($query) => $query->where('user_id', auth()->user()->getKey()))
            );
    }

    protected function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label(__('general.topic_name'))
                ->placeholder(__('general.topic_name'))
                ->required(),
            CheckboxList::make('user_id')
                ->label(__('general.participants'))
                ->default([Auth::id()])
                ->rules('required|array|min:2')
                ->options(function () {
                    $submissionParticipant = $this->submission->participants()
                        ->with(['user', 'role'])
                        ->get()
                        ->mapWithKeys(fn($participant) => [$participant->user->getKey() => $participant->user->fullName . ' (' . $participant->role->name . ')'])
                        ->toArray();

                    if (! isset($submissionParticipant[Auth::id()])) {
                        $submissionParticipant[Auth::id()] = Auth::user()->fullName . ' (Unassigned)';
                    }

                    return $submissionParticipant;
                }),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('general.discussion'))
            ->query(fn() => $this->getEloquentQuery())
            ->recordAction('open-discussion-detail')
            ->actions([
                ActionGroup::make([
                    Action::make('open-discussion-detail')
                        ->icon('lineawesome-eye-solid')
                        ->label(__('general.details'))
                        ->modalWidth('6xl')
                        ->modalHeading(fn(Model $discussionTopic): string => __('general.discussion_for_topic', ['variable' => $discussionTopic->name]))
                        ->modalSubmitAction(false)
                        ->infolist(function (Model $discussionTopic) {
                            return [
                                Livewire::make(
                                    DiscussionDetail::class,
                                    ['topic' => $discussionTopic]
                                )->lazy(),
                                Fieldset::make('form-discussion-detail')
                                    ->label(__('general.add_message'))
                                    ->columns(1)
                                    ->visible(fn ($record): bool => $record->open)
                                    ->schema([
                                        Livewire::make(
                                            DiscussionDetailForm::class,
                                            ['topic' => $discussionTopic]
                                        )->lazy(),
                                    ]),
                            ];
                        }),
                    Action::make('update-topic')
                        ->label(__('general.edit'))
                        ->icon('lineawesome-edit-solid')
                        ->mountUsing(function ($record, Form $form) {
                            $form->fill([
                                'name' => $record->name,
                                'user_id' => $record->participants()->pluck('user_id')->toArray(),
                            ]);
                        })
                        ->authorize(fn($record) => auth()->user()->can('update', $record))
                        ->form(fn(Form $form) => $this->form($form))
                        ->successNotificationTitle(__('general.topic_updated_successfully'))
                        ->action(function (Action $action, array $data, Model $record) {
                            UpdateDiscussionTopic::run(
                                $record,
                                ['name' => $data['name']],
                                $data['user_id']
                            );
                            $action->success();
                        }),
                    Action::make('close')
                        ->authorize(fn($record) => auth()->user()->can('close', $record))
                        ->label(fn($record): string => $record->open ? __('general.close') : __('general.open'))
                        ->color(fn($record): string => $record->open ? 'warning' : 'success')
                        ->icon(fn($record): string => $record->open ? 'lineawesome-lock-solid' : 'lineawesome-unlock-solid')
                        ->requiresConfirmation()
                        ->successNotificationTitle(__('general.topic_updated_successfully'))
                        ->action(function (Action $action, $record) {
                            $record->update(['open' => ! $record->open]);
                            $action->success();
                        }),
                    DeleteAction::make()
                        ->authorize('DiscussionTopic:delete'),
                ]),
            ])
            ->headerActions([
                Action::make('create-topic')
                    ->authorize('create', ModelsDiscussionTopic::class)
                    ->icon('lineawesome-plus-solid')
                    ->outlined()
                    ->label(__('general.topic'))
                    ->modalWidth('xl')
                    ->form(fn($form) => $this->form($form))
                    ->successNotificationTitle(__('general.topic_created_successfully'))
                    ->failureNotificationTitle(__('general.topic_createtion_failed'))
                    ->action(function (Action $action, array $data, Form $form) {
                        $form->validate();

                        $topic = CreateDiscussionTopic::run(
                            $this->submission,
                            [
                                'name' => $data['name'],
                                'stage' => $this->stage,
                            ],
                            $data['user_id']
                        );

                        try {
                            $topic->participants()
                                ->with('user')
                                ->get()
                                ->each(function ($participant) use ($topic) {
                                    $participant->user->notify(
                                        new NewDiscussionTopic($topic)
                                    );
                                });
                        } catch (\Throwable $th) {
                            $action->failureNotificationTitle(__('general.failed_to_send_notification_to_participants'));
                            $action->failure();
                        } finally {
                            $action->success();
                        }
                    }),
            ])
            ->columns([
                BadgeableColumn::make('name')
                    ->label(__('general.name'))
                    ->wrap()
                    ->suffixBadges([
                        Badge::make('status')
                            ->label(fn($record) => $record->open ? __('general.open') : __('general.closed'))
                            ->color(fn($record) => $record->open ? 'success' : 'danger'),
                    ]),
                TextColumn::make('Last Update')
                    ->label(__('general.last_update'))
                    ->getStateUsing(fn($record) => $record->getLastSender()?->fullName ?? '-')
                    ->description(fn($record): ?string => $record->getLastUpdate()),
            ]);
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.discussions.discussion-topic');
    }
}
