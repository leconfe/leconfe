<?php

namespace App\Panel\Conference\Livewire\Workflows\Components;

use App\Panel\Conference\Livewire\Workflows\Concerns\CanOpenStage;
use App\Panel\Conference\Livewire\Workflows\Concerns\InteractWithTenant;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

/**
 * TODO:
 * - Create job to check if stage is open if it's scheduled
 */
class StageSchedule extends Component implements HasActions, HasForms
{
    use CanOpenStage, InteractsWithActions, InteractsWithForms, InteractWithTenant;

    public string $stage;

    public function mount(string $stage)
    {
        $this->stage = $stage;
    }

    public function closeAction()
    {
        return Action::make('closeAction')
            ->hidden(
                fn (): bool => ! $this->isStageOpen()
            )
            ->modalWidth('xl')
            ->modalAlignment('center')
            ->icon('iconpark-internaltransmission-o')
            ->label(__('translation.stageSchedule.closeActionLabelClose'))
            ->requiresConfirmation()
            ->modalHeading(__('translation.stageSchedule.closeActionModalHeading'))
            ->modalDescription(__('translation.stageSchedule.closeActionModalDescription'))
            ->modalIconColor('danger')
            ->successNotificationTitle(__('translation.stageSchedule.closeActionsuccessNotificationTitle'))
            ->action(function (Action $action) {
                $this->closeStage();
                $action->success();
            });
    }

    public function openAction()
    {
        return Action::make('openAction')
            ->hidden(
                fn (): bool => $this->isStageOpen()
            )
            ->icon('iconpark-externaltransmission')
            ->label(__('translation.stageSchedule.openActionLabelOpen'))
            ->requiresConfirmation()
            ->modalHeading(__('translation.stageSchedule.openActionModalHeading'))
            ->modalDescription(__('translation.stageSchedule.openActionModalDescription'))
            ->successNotificationTitle(__('translation.stageSchedule.openActionSuccessNotificationTitle'))
            ->modalIconColor('success')
            ->action(function (Action $action) {
                $this->openStage();
                $action->success();
            });
    }

    public function scheduleAction()
    {
        return Action::make('scheduleAction')
            ->label(__('translation.stageSchedule.scheduleActionLabelSchedules'))
            ->icon('iconpark-calendar-o')
            ->modalWidth('xl')
            ->mountUsing(function (Form $form) {
                $form->fill([
                    'start_date' => $this->getSetting('start_date'),
                    'end_date' => $this->getSetting('end_date'),
                ]);
            })
            ->form([
                DatePicker::make('start_date')
                    ->label(__('translation.stageSchedule.scheduleActionLabelDateStart'))
                    ->required()
                    ->native(false)
                    ->displayFormat('d-F-Y')
                    ->default(now())
                    ->maxDate(now()->addYear()),
                DatePicker::make('end_date')
                    ->label(__('translation.stageSchedule.scheduleActionLabelDateEnd'))
                    ->required()
                    ->native(false)
                    ->displayFormat('d-F-Y')
                    ->default(now())
                    ->maxDate(now()->addYear()),
            ])
            ->successNotificationTitle(__('translation.stageSchedule.successNotificationTitleScheduled'))
            ->action(function (array $data, Action $action) {
                $this->setSchedule(
                    $data['start_date'],
                    $data['end_date']
                );
                $action->success();
            });
    }

    // public function start()
    // {
    //     $this->conference->setMeta("workflow.{$this->stage}.open", true);
    //     $this->conference->setMeta("workflow.{$this->stage}.start_date", now());

    //     Notification::make()
    //         ->title("Success")
    //         ->body("Workflow {$this->stage} schedule updated successfully")
    //         ->success();
    // }

    // public function end()
    // {
    //     $this->conference->setMeta("workflow.{$this->stage}.open", false);
    //     $this->conference->setMeta("workflow.{$this->stage}.end_date", now());

    //     Notification::make()
    //         ->title("Success")
    //         ->body("Workflow {$this->stage} schedule updated successfully")
    //         ->success();
    // }

    public function render()
    {
        return view('panel.conference.livewire.workflows.components.stage-schedule');
    }
}
