<?php

namespace App\Panel\Conference\Livewire\Workflows;

use App\Panel\Conference\Livewire\Workflows\Base\WorkflowStage;
use Awcodes\Shout\Components\Shout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class EditingSetting extends WorkflowStage implements HasForms
{
    use InteractsWithForms;

    protected ?string $stage = 'editing';

    protected ?string $stageLabel = 'Editing';

    public array $settings = [];

    public function mount()
    {
        $this->form->fill([
            'settings' => [
                'production_allowed_file_types' => $this->getSetting('production_allowed_file_types', ['pdf']),
                'draft_allowed_file_types' => $this->getSetting('draft_allowed_file_types', ['pdf', 'doc', 'docx']),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Shout::make('settings.stage-closed')
                    ->hidden(fn (): bool => $this->isStageOpen())
                    ->color('warning')
                    ->content(
                        __('translation.editingSetting.editingSettingContentThe') . 
                        $this->getStageLabel() . 
                        __('translation.editingSetting.editingSettingContentIsNotOpenYetStart')
                    ),
                    
                Grid::make()
                    ->schema([
                        TagsInput::make('settings.draft_allowed_file_types')
                            ->label(__('translation.editingSetting.editingSettingLabelDraftFileType'))
                            ->helperText(__('translation.editingSetting.editingSettingHelperTextDraftFileType'))
                            ->splitKeys([',']),
                        TagsInput::make('settings.production_allowed_file_types')
                            ->label(__('translation.editingSetting.editingSettingLabelProductionFileType'))
                            ->helperText(__('translation.editingSetting.editingSettingHelperTextProductionFileType'))
                            ->splitKeys([',']),
                    ])
                    ->columns(1),
            ]);
    }

    public function save()
    {
        $data = $this->form->getState();
        foreach ($data['settings'] as $settingName => $settingValue) {
            $this->updateSetting($settingName, $settingValue);
        }

        Notification::make('editing-saved')
            ->title(__('translation.button.save'))
            ->body(__('translation.editingSetting.editingSettingBodyNotification'))
            ->success()
            ->send();
    }

    public function render()
    {
        return view('panel.conference.livewire.workflows.editing-setting');
    }
}
