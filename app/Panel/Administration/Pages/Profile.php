<?php

namespace App\Panel\Administration\Pages;

use App\Actions\User\UserUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Infolists\Components\BladeEntry;
use App\Models\Enums\UserRole;
use App\Models\User;
use App\Panel\Conference\Livewire\Forms\Conferences\ContributorForm;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'panel.administration.pages.profile';

    protected static bool $shouldRegisterNavigation = false;

    public array $informationFormData = [];

    public array $rolesFormData = [];

    public array $notificationFormData = [];

    public function mount(): void
    {
        $user = auth()->user();
        $meta = $user->getAllMeta()->toArray();

        $this->informationForm->fill([
            ...$user->attributesToArray(),
            'meta' => $meta,
        ]);

        $this->rolesForm->fill([
            'roles' => $user->roles->filter(fn ($role) => in_array($role->name, UserRole::getAllowedSelfAssignRoleNames()))->pluck('name')->toArray(),
            'meta' => $meta,
        ]);

        $this->notificationForm->fill([
            'meta' => $meta,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'informationForm',
            'rolesForm',
            'notificationForm',
        ];
    }

    public function informationForm(Form $form): Form
    {
        return $form
            ->model(auth()->user())
            ->schema([
                Section::make()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('profile')
                            ->label(__('general.profile_photo'))
                            ->collection('profile')
                            ->avatar()
                            ->columnSpan(['lg' => 2]),
                        MultilanguageComponent::make([
                            TextInput::make('given_name')
                            ->label(__('general.given_name'))
                            ->required(),
                            TextInput::make('family_name')
                            ->label(__('general.family_name')),
                            TextInput::make('meta.public_name')
                            ->label(__('general.public_name'))
                            ->helperText(__('general.public_name_helper'))
                            ->columnSpan(['lg' => 2]),
                        ]),
                        
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->columnSpan(['lg' => 2])
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label(__('general.password'))
                            ->required(fn (?User $record) => ! $record)
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label(__('general.password_confirmation'))
                            ->requiredWith('password')
                            ->password()
                            ->dehydrated(false),
                        ...ContributorForm::additionalFormField(),
                    ])
                    ->columns(2),
            ])
            ->statePath('informationFormData');
    }

    public function submitInformationForm()
    {
        $data = $this->informationForm->getState();
        try {
            UserUpdateAction::run(auth()->user(), $data);
            Notification::make()
                ->success()
                ->title(__('general.saved'))
                ->send();
        } catch (\Throwable $th) {
            Notification::make()
                ->danger()
                ->title(__('general.failed_to_save'))
                ->send();
        }
    }

    public function rolesForm(Form $form): Form
    {
        return $form
            ->model(auth()->user())
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        CheckboxList::make('roles')
                            ->label(__('general.roles'))
                            ->options(UserRole::getAllowedSelfAssignRoleNames()),
                        TagsInput::make('meta.reviewing_interests')
                            ->label(__('general.reviewing_interests'))
                            ->placeholder('')
                            ->columnSpan([
                                'lg' => 2,
                            ]),
                    ]),

            ])
            ->statePath('rolesFormData');
    }

    public function submitRolesForm()
    {
        $data = $this->rolesForm->getState();
        try {
            $user = auth()->user();

            UserUpdateAction::run($user, $data);

            foreach (UserRole::selfAssignedRoleValues() as $roleName) {
                if ($user->hasRole($roleName) && ! in_array($roleName, $data['roles'])) {
                    $user->removeRole($roleName);
                } elseif (! $user->hasRole($roleName) && in_array($roleName, $data['roles'])) {
                    $user->assignRole($roleName);
                }
            }

            Notification::make()
                ->success()
                ->title(__('general.saved'))
                ->send();
        } catch (\Throwable $th) {
            Notification::make()
                ->danger()
                ->title(__('general.failed_to_save'))
                ->send();
            throw $th;
        }
    }

    public function notificationForm(Form $form)
    {
        return $form
            ->statePath('notificationFormData')
            ->schema([
                Section::make(__('general.new_announcement'))
                    ->description(__('general.notifications_announcement_send'))
                    ->schema([
                        Checkbox::make('meta.notification.enable_new_announcement_email')
                            ->label(__('general.enable_email_notification')),
                    ])
                    ->aside(),
            ]);
    }

    public function submitNotificationsForm()
    {
        $data = $this->notificationForm->getState();

        try {
            $user = auth()->user();

            UserUpdateAction::run($user, $data);

            Notification::make()
                ->success()
                ->title(__('general.saved'))
                ->send();
        } catch (\Throwable $th) {
            Notification::make()
                ->danger()
                ->title(__('general.failed_to_save'))
                ->send();
            throw $th;
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make()
                    ->schema([
                        Tab::make('Information')
                            ->label(__('general.information'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                BladeEntry::make('information-form')
                                    ->blade('
                                    <form wire:submit="submitInformationForm" class="space-y-4">
                                        {{ $this->informationForm }}

                                        <x-filament::button type="submit">
                                            {{ __("general.save")}}
                                        </x-filament::button>
                                    </form>
                                '),
                            ]),
                        Tab::make('Roles')
                            ->label(__('general.roles'))
                            ->icon('heroicon-o-shield-check')
                            ->hidden(! app()->getCurrentScheduledConference())
                            ->schema([
                                BladeEntry::make('roles-form')
                                    ->blade('
                                        <form wire:submit="submitRolesForm" class="space-y-4">
                                            @if (empty(auth()->user()->roles->pluck("name")->toArray()))
                                            <x-filament::section class="!bg-primary-100">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <x-heroicon-o-globe-alt class="w-8 h-8 text-primary-800" />
                                                        <div class="flex flex-col ml-3">
                                                            <div class="text-sm font-medium leading-none">{{ __("general.register_for_conference")}}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </x-filament::section>
                                            @endif
                                            {{ $this->rolesForm }}

                                            <x-filament::button type="submit">
                                                {{ __("general.save")}}
                                            </x-filament::button>
                                        </form>
                                    '),
                            ]),
                        Tab::make('Notifications')
                            ->label(__('general.notification'))
                            ->icon('heroicon-o-bell')
                            ->schema([
                                BladeEntry::make('notification-form')
                                    ->blade('
                                        <form wire:submit="submitNotificationsForm" class="space-y-4">
                                            <x-filament::section>
                                                {{ $this->notificationForm }}
                                            </x-filament::section>

                                            <x-filament::button type="submit">
                                                {{ __("general.save")}}
                                            </x-filament::button>
                                        </form>
                                    '),
                            ]),
                    ])
                    ->contained(false)
                    ->persistTab()
                    ->persistTabInQueryString(),
            ]);
    }
}
