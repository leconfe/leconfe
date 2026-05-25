<?php

namespace App\Panel\Conference\Resources\UserResource\Pages;

use App\Forms\Components\TinyEditor;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Panel\Conference\Resources\UserResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use App\Mail\MailUser;

class ListUsers extends ListRecords implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = UserResource::class;
    protected static string $view = 'panel.conference.resources.user-resource.pages.list-users';

    public ?array $notifyFormData = [];

    public function mount(): void
    {
        parent::mount();

        $this->notifyForm->fill();
    }

    public function getView(): string
    {
        if (app()->isOnSite()) {
            return static::$view;
        }
        return 'panel.conference.resources.user-resource.pages.list-users';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getInvitationPendingCountProperty(): int
    {
        $conferenceId = app()->getCurrentConferenceId();
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId();

        return UserInvitation::query()
            ->when($scheduledConferenceId, fn (Builder $query) => $query->where('scheduled_conference_id', $scheduledConferenceId))
            ->when(! $scheduledConferenceId && $conferenceId, fn (Builder $query) => $query
                ->where('conference_id', $conferenceId)
                ->whereNull('scheduled_conference_id'))
            ->where('status', 'pending')
            ->count();
    }

    protected function getForms(): array
    {
        return [
            'notifyForm',
        ];
    }

    public function notifyForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('role_ids')
                    ->label(__('general.roles'))
                    ->options(
                        Role::where('name', '!=', UserRole::Admin)
                            ->pluck('name', 'id')
                    )
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText(__('general.send_notification_description'))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('subject')
                    ->label(__('general.subject'))
                    ->required()
                    ->default('')
                    ->maxLength(255),
                TinyEditor::make('message')
                    ->label(__('general.message'))
                    ->default('')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->statePath('notifyFormData');
    }

    public function sendNotification()
    {
        $data = $this->notifyForm->getState();
        if (app()->isOnScheduledConference()) {
            $fromName = app()->getCurrentScheduledConference()->title;
        } else {
            $fromName = app()->getCurrentConference()->name;
        }

        try {
            $users = User::whereHas('roles', function ($query) use ($data) {
                $query->whereIn('roles.id', $data['role_ids'] ?? []);
            })->get();
            $subject = $data['subject'] ?? '';
            $message = $data['message'] ?? '';

            foreach ($users as $user) {
                Notification::make()
                    ->title($subject)
                    ->body($message)
                    ->sendToDatabase($user);

                try {
                    if (!empty($user->email)) {
                        Mail::to($user->email)
                            ->send((new MailUser($subject, $message))->from(config('mail.from.address'), $fromName));
                    }
                } catch (\Throwable $e) {
                    // ignore individual mail failures
                    \Illuminate\Support\Facades\Log::error("Failed to send email to user {$user->id}: " . $e->getMessage());
                }
            }

            Notification::make()
                ->success()
                ->title(__('general.notification_sent'))
                ->send();

            $this->notifyForm->fill([]);
        } catch (\Throwable $th) {
            Notification::make()
                ->danger()
                ->title(__('general.failed_to_send'))
                ->body($th->getMessage())
                ->send();
        }
    }
}
