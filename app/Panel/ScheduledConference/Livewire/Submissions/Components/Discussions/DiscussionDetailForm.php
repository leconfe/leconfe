<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Discussions;

use App\Models\DiscussionTopic;
use Awcodes\Shout\Components\Shout;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class DiscussionDetailForm extends \Livewire\Component implements HasForms
{
    use InteractsWithForms;

    public DiscussionTopic $topic;

    public string $message;

    public array $attachments = [];

    public function mount()
    {
        $this->form->fill([
            'message' => '',
            'attachments' => [],
        ]);
    }

    public function submit()
    {
        $this->form->validate();

        if (! $this->topic->open) {
            return abort('403', __('general.discussion_is_closed'));
        }

        $formData = $this->form->getState();

        $discussion = $this->topic->discussions()->create($formData);

        $this->form->model($discussion)->saveRelationships();

        Notification::make()
            ->success()
            ->title(__('general.discussion_added'))
            ->body(__('general.discussion_added_has_been_added_succesfully'))
            ->send();

        $this->form->fill([
            'message' => '',
            'attachments' => [],
        ]); // Reset Form Input

        $this->dispatch('refreshMessages');
    }

    public function form(Form $form)
    {
        return $form
            ->disabled(fn (): bool => ! $this->topic->open)
            ->schema([
                Shout::make('discussion-alert')
                    ->type('warning')
                    ->hidden(fn (): bool => $this->topic->open)
                    ->content(__('general.can_not_add_message_to_closed_discussion')),
                Textarea::make('message')
                    ->label(__('general.message'))
                    ->placeholder(__('general.message'))
                    ->columnSpanFull()
                    ->required()
                    ->rows(5),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label(__('general.attachments'))
                    ->collection('discussion-attachment')
                    ->disk('private-files')
                    ->dehydrated()
                    ->preserveFilenames()
                    ->multiple()
                    ->previewable(false)
                    ->downloadable()
                    ->visibility('private'),
            ]);
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.discussions.discussion-detail-form');
    }
}
