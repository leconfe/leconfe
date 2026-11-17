<?php

namespace App\Livewire;

use App\Services\EmailPreferences\Categories;
use App\Services\EmailPreferences\ContextAccess;
use App\Services\EmailPreferences\Preferences;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;

class EmailPreferencesForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected ?array $contexts = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        $contexts = $this->contexts();
        $current = app()->getCurrentConferenceId().':'.(app()->getCurrentScheduledConferenceId() ?? 0);

        $this->loadScope(isset($contexts[$current]) ? $current : array_key_first($contexts));
    }

    /**
     * @return array<string, string>
     */
    public function contexts(): array
    {
        return $this->contexts ??= app(ContextAccess::class)->contexts(auth()->user());
    }

    public function loadScope(?string $scope): void
    {
        $values = ['scope' => $scope, 'preferences' => []];

        if ($scope !== null) {
            [$conferenceId, $scheduledConferenceId] = $this->parseScope($scope);

            foreach (Categories::all() as $category) {
                $values['preferences'][$category] = app(Preferences::class)
                    ->enabled(auth()->user(), $conferenceId, $scheduledConferenceId, $category);
            }
        }

        $this->form->fill($values);
    }

    public function form(Form $form): Form
    {
        $sections = [
            Section::make(__('email_preferences.scope'))
                ->description(__('email_preferences.scope_help'))
                ->schema([
                    Select::make('scope')
                        ->label(__('email_preferences.scope'))
                        ->hiddenLabel()
                        ->options(fn () => $this->contexts())
                        ->searchable()
                        ->required()
                        ->live()
                        ->selectablePlaceholder(false)
                        ->afterStateUpdated(fn (?string $state) => $this->loadScope($state)),
                ])
                ->aside(),
        ];

        foreach (Categories::all() as $category) {
            $isAnnouncement = $category === Categories::ANNOUNCEMENT;

            $sections[] = Section::make($isAnnouncement ? __('general.new_announcement') : __("email_preferences.categories.{$category}"))
                ->description($isAnnouncement ? __('general.notifications_announcement_send') : __("email_preferences.descriptions.{$category}"))
                ->schema([
                    Checkbox::make("preferences.{$category}")
                        ->label(__('general.enable_email_notification'))
                        ->rules(['boolean']),
                ])
                ->aside();
        }

        return $form
            ->statePath('data')
            ->schema($sections);
    }

    public function save(): void
    {
        // Authorization is checked again on every request, in case membership
        // was revoked after the form was rendered.
        [$conferenceId, $scheduledConferenceId] = $this->parseScope($this->data['scope'] ?? null);

        $state = $this->form->getState();

        app(Preferences::class)->save(auth()->user(), $conferenceId, $scheduledConferenceId, $state['preferences']);

        Notification::make()
            ->success()
            ->title(__('email_preferences.saved'))
            ->send();
    }

    public function render()
    {
        return view('livewire.email-preferences-form');
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function parseScope(?string $scope): array
    {
        abort_unless($scope && preg_match('/^[1-9][0-9]*:[1-9][0-9]*$/D', $scope), 403);

        [$conferenceId, $scheduledConferenceId] = array_map('intval', explode(':', $scope));

        abort_unless(app(ContextAccess::class)->allows(auth()->user(), $conferenceId, $scheduledConferenceId), 403);

        return [$conferenceId, $scheduledConferenceId];
    }
}
