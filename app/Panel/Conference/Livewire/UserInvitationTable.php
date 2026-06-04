<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\UserInvitation\InviteUserAction;
use App\Mail\Templates\UserRoleInvitationMail;
use App\Models\Role;
use App\Models\UserInvitation;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Get;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Js;
use Livewire\Component;

class UserInvitationTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->heading(__('general.user_invitations'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('general.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role_name')
                    ->label(__('general.role'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('general.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'pending' => 'warning',
                        'expired' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label(__('general.invited_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('general.expires_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('general.pending'),
                        'accepted' => __('general.accepted'),
                        'expired' => __('general.expired'),
                        'cancelled' => __('general.cancelled'),
                    ]),
                SelectFilter::make('role_name')
                    ->label(__('general.role'))
                    ->options(fn () => $this->getRoleNameOptions()),
            ])
            ->headerActions([
                Action::make('inviteUser')
                    ->label(__('general.invite_user'))
                    ->icon('heroicon-o-envelope')
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->authorize(fn () => $this->canInviteUsers())
                    ->form([
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->email()
                            ->required()
                            ->rule(function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $roleId = $get('role_id');
                                    $role = Role::query()
                                        ->withoutGlobalScopes()
                                        ->availableRolesByContext()
                                        ->whereKey($roleId)
                                        ->first();

                                    if (! $role || ! $value) {
                                        return;
                                    }

                                    $existsPendingInvitation = UserInvitation::query()
                                        ->where('email', mb_strtolower(trim((string) $value)))
                                        ->where('role_name', $role->name)
                                        ->where('conference_id', $role->conference_id ?: null)
                                        ->where('scheduled_conference_id', $role->scheduled_conference_id ?: null)
                                        ->whereNull('track_id')
                                        ->where('status', 'pending')
                                        ->where('expires_at', '>', now())
                                        ->exists();

                                    if ($existsPendingInvitation) {
                                        $fail('A pending invitation already exists for this email and role.');
                                    }
                                };
                            }),
                        Select::make('role_id')
                            ->label(__('general.role'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn () => $this->getRoleOptions())
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if (! $value) {
                                        return;
                                    }

                                    $roleExists = Role::query()
                                        ->withoutGlobalScopes()
                                        ->availableRolesByContext()
                                        ->whereKey($value)
                                        ->exists();

                                    if (! $roleExists) {
                                        $fail('Selected role is not available in the current context.');
                                    }
                                };
                            })
                            ->native(false),
                    ])
                    ->action(fn (array $data) => InviteUserAction::run($data))
                    ->successNotificationTitle('Invitation created successfully.'),
            ])
            ->emptyStateHeading(__('general.no_user_invitations'))
            ->emptyStateDescription(__('general.no_user_invitations_description'))
            ->actions([
                ActionGroup::make([
                    Action::make('copy_link')
                        ->label('Copy Link')
                        ->icon('heroicon-o-link')
                        ->authorize(fn () => $this->canInviteUsers())
                        ->visible(fn (UserInvitation $record) => $record->status === 'pending')
                        ->alpineClickHandler(fn (UserInvitation $record) => sprintf(
                            'window.navigator.clipboard.writeText(%s)',
                            Js::from($record->getAcceptUrl()),
                        )),
                    Action::make('resend')
                        ->label(__('general.resend'))
                        ->icon('heroicon-o-paper-airplane')
                        ->authorize(fn () => $this->canInviteUsers())
                        ->visible(fn (UserInvitation $record) => $record->status === 'pending')
                        ->action(function (UserInvitation $record) {
                            $record->update([
                                'expires_at' => now()->addDays(7),
                            ]);

                            Mail::to($record->email)->send(new UserRoleInvitationMail($record->fresh()));

                            Notification::make()
                                ->title('Invitation resent.')
                                ->success()
                                ->send();
                        }),
                    Action::make('cancel')
                        ->label(__('general.cancel'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn () => $this->canInviteUsers())
                        ->visible(fn (UserInvitation $record) => $record->status === 'pending')
                        ->action(function (UserInvitation $record) {
                            $record->update([
                                'status' => 'cancelled',
                            ]);

                            Notification::make()
                                ->title('Invitation cancelled.')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    protected function getQuery(): Builder
    {
        $conferenceId = app()->getCurrentConferenceId();
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId();

        return UserInvitation::query()
            ->with(['invitedBy.meta', 'conference', 'scheduledConference'])
            ->when($scheduledConferenceId, fn (Builder $query) => $query->where('scheduled_conference_id', $scheduledConferenceId))
            ->when(! $scheduledConferenceId && $conferenceId, fn (Builder $query) => $query
                ->where('conference_id', $conferenceId)
                ->whereNull('scheduled_conference_id'))
            ->latest('id');
    }

    protected function getRoleOptions(): array
    {
        return Role::query()
            ->withoutGlobalScopes()
            ->availableRolesByContext()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getRoleNameOptions(): array
    {
        return Role::query()
            ->withoutGlobalScopes()
            ->availableRolesByContext()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->toArray();
    }

    protected function canInviteUsers(): bool
    {
        return auth()->user()?->can('User:invite') ?? false;
    }
}
