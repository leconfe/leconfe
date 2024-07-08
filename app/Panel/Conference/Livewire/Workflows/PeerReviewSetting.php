<?php

namespace App\Panel\Conference\Livewire\Workflows;

use App\Panel\Conference\Livewire\Workflows\Base\WorkflowStage;
use Awcodes\Shout\Components\Shout;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;

class PeerReviewSetting extends WorkflowStage implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    protected ?string $stage = 'peer-review';

    protected ?string $stageLabel = 'Peer Review';

    public function mount()
    {
        $this->form->fill([
            'settings' => [
                'allowed_file_types' => $this->getSetting('allowed_file_types', ['pdf', 'docx', 'doc']),
                'start_at' => $this->getSetting('start_at', now()->addDays(1)->format('d F Y')),
                'end_at' => $this->getSetting('end_at', now()->addDays(14)->format('d F Y')),
                'invitation_response_days' => $this->getSetting('invitation_response_days', 14),
            ],
        ]);
    }

    public function submitAction()
    {
        return Action::make('submitAction')
            ->icon('lineawesome-save-solid')
            ->label(__('translation.button.save'))
            ->successNotificationTitle(__('translation.peerReviewSetting.successNotificationTitleSettingSaved'))
            ->action(function (Action $action) {
                $this->form->validate();
                $data = $this->form->getState();
                foreach ($data['settings'] as $key => $value) {
                    $this->updateSetting($key, $value);
                }
                $action->success();
            });
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Shout::make('settings.stage-closed')
                    ->hidden(fn (): bool => $this->isStageOpen())
                    ->color('warning')
                    ->content(__('translation.peerReviewSetting.contentPeerReviewSetting')),
                Grid::make()
                    ->schema([
                        TagsInput::make('settings.allowed_file_types')
                            ->label(__('translation.peerReviewSetting.labelAllowedFileTypes'))
                            ->helperText(__('translation.peerReviewSetting.helperTextAllowedFileTypes'))
                            ->splitKeys([',', 'enter', ' ']),
                        /**
                         * Question:
                         * 1. Should add min and max size?
                         * 2. Should add max number of files?
                         * 3. is the acceptedFileTypes is enough?
                         */
                        SpatieMediaLibraryFileUpload::make('settings.paper_templates')
                            ->model($this->conference)
                            ->previewable(false)
                            ->downloadable()
                            ->disk('private-files')
                            ->preserveFilenames()
                            ->visibility('private')
                            ->collection('paper-templates')
                            ->acceptedFileTypes(
                                ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                            )
                            ->helperText(__('translation.peerReviewSetting.helperTextUploadPaperTemplates'))
                            ->saveRelationshipsUsing(
                                static fn (SpatieMediaLibraryFileUpload $component) => $component->saveUploadedFiles()
                            )
                            ->label(__('translation.peerReviewSetting.labelPaperTemplates')),
                        TextInput::make('settings.invitation_response_days')
                            ->label(__('translation.peerReviewSetting.labelInvitationResponseDeadline'))
                            ->default(14)
                            ->helperText(__('translation.peerReviewSetting.helperTextDeadlineForReviewersToRespond'))
                            ->numeric()
                            ->minLength(2)
                            ->columns(1)
                            ->suffix(__('translation.peerReviewSetting.suffixDays')),
                        Fieldset::make(__('translation.peerReviewSetting.fieldsetReviewDeadline'))
                            ->schema([
                                DatePicker::make('settings.start_at')
                                    ->label(__('translation.peerReviewSetting.labelDateStart')),
                                DatePicker::make('settings.end_at')
                                    ->label(__('translation.peerReviewSetting.labelDateEnd')),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.workflows.peer-review-setting');
    }
}
