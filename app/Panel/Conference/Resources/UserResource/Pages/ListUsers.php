<?php

namespace App\Panel\Conference\Resources\UserResource\Pages;

use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Panel\Conference\Resources\UserResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Mail;
use App\Mail\MailUser;

class ListUsers extends ListRecords implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = UserResource::class;

    public array $notifyFormData = [
        'role_ids' => [],
        'subject' => '',
        'message' => '',
    ];

    public function getView(): string
    {
        if (app()->isOnSite()) {
            return static::$view;
        }
        return 'panel.conference.resources.user-resource.pages.list-users';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
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
            ->statePath('notifyFormData')
            ->schema([
                Forms\Components\Select::make('role_ids')
                    ->label(__('general.roles'))
                    ->options(
                        Role::where('name', '!=', UserRole::Admin)
                            ->pluck('name', 'id')
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(__('general.send_notification_description'))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('subject')
                    ->label(__('general.subject'))
                    ->required()
                    ->default('')
                    ->maxLength(255),
                Forms\Components\RichEditor::make('message')
                    ->label(__('general.message'))
                    ->required()
                    ->default('')
                    ->columnSpanFull(),
            ]);
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

            foreach ($users as $user) {
                $subject = $data['subject'] ?? '';
                $message = $data['message'] ?? '';

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
