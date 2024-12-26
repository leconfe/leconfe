<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Actions\Submissions\SubmissionCreateAction;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\Submission;
use App\Models\Timeline;
use App\Models\Track;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CreateSubmission extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Make a Submission';

    protected static string $resource = SubmissionResource::class;

    protected static string $view = 'panel.conference.resources.submission-resource.pages.create-submission';

    public $data;

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    protected function getViewData(): array
    {
        return [
            'isOpen' => Timeline::isSubmissionOpen() && Track::query()
                ->active()
                ->when(! auth()->user()->hasAnyRole([
                    UserRole::Admin,
                    UserRole::ConferenceManager,
                    UserRole::ScheduledConferenceEditor,
                    UserRole::TrackEditor,
                ]), fn ($query) => $query->whereMeta('submit_only_for_editors', false))
                ->count(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('before_you_begin')
                    ->label(__('general.before_you_begin'))
                    ->extraAttributes(['class' => 'prose prose-sm max-w-none'])
                    ->visible(fn () => app()->getCurrentScheduledConference()->getMeta('before_you_begin') !== null)
                    ->content(fn () => new HtmlString(app()->getCurrentScheduledConference()->getMeta('before_you_begin'))),
                TextInput::make('meta.title')
                    ->required(),
                Radio::make('track_id')
                    ->label(__('general.track'))
                    ->required()
                    ->visible(fn ($component) => count($component->getOptions()) > 1)
                    ->options(
                        fn () => Track::query()
                            ->active()
                            ->whereMeta('submit_only_for_editors', auth()->user()->hasRole([
                                UserRole::Admin,
                                UserRole::ConferenceManager,
                                UserRole::ScheduledConferenceEditor,
                                UserRole::TrackEditor,
                            ]))
                            ->get()
                            ->pluck('title', 'id')
                    )
                    ->reactive(),
                Placeholder::make('track_policy')
                    ->extraAttributes(['class' => 'prose prose-sm max-w-none'])
                    ->visible(function (Get $get) {
                        if (! $get('track_id')) {
                            return false;
                        }

                        $track = Track::find($get('track_id'));
                        if ($track->getMeta('policy') === null) {
                            return false;
                        }

                        return true;
                    })
                    ->label(function (Get $get) {
                        if (! $get('track_id')) {
                            return '';
                        }

                        return Track::find($get('track_id'))->title;
                    })
                    ->content(fn (Get $get) => $get('track_id') ? new HtmlString(Track::find($get('track_id'))->getMeta('policy')) : ''),
                Fieldset::make(__('general.submission_checklist'))
                    ->columns(1)
                    ->schema([
                        Placeholder::make('submission_checklist')
                            ->hiddenLabel()
                            ->extraAttributes(['class' => 'prose prose-sm'])
                            ->visible(fn () => app()->getCurrentScheduledConference()->getMeta('submission_checklist') !== null)
                            ->content(fn () => new HtmlString(app()->getCurrentScheduledConference()->getMeta('submission_checklist'))),
                        Checkbox::make('submissionRequirements')
                            ->required()
                            ->label(__('general.submission_meets_all_of_requirements')),
                    ]),
                Section::make(__('general.privacy_consent'))
                    ->schema([
                        Checkbox::make('privacy_consent')
                            ->inline()
                            ->required()
                            ->label(__('general.agree_to_my_collected_stored_to_privacy_statement')),
                    ]),
                Radio::make('user_group_id')
                    ->label(__('general.submit_as'))
                    ->hidden(fn ($component) => count($component->getOptions()) < 2 || ! auth()->user()->can('submitAs', Submission::class))
                    ->options(function () {
                        $managerUserGroupAssignments = Role::query()->get()->filter(fn ($role) => auth()->user()->hasRole($role) && $role->hasDefaultPermission('Submission:submitAs'));
                        $authorUserGroupAssignments = Role::query()->where('name', UserRole::Author)->get();

                        return $managerUserGroupAssignments->merge($authorUserGroupAssignments)->pluck('name', 'id');
                    }),
            ])
            ->model(Submission::class)
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();

        if (! auth()->user()->hasRole(UserRole::Author)) {
            auth()->user()->assignRole(UserRole::Author);
        }

        try {
            $submission = SubmissionCreateAction::run($data);

            $submitAsRole = Role::query()
                ->when(
                    array_key_exists('user_group_id', $data),
                    fn ($query) => $query->where('id', $data['user_group_id']),
                    fn ($query) => $query->where('name', UserRole::Author)
                )
                ->first();

            if ($submitAsRole === null) {
                throw new \Exception('Role not found');
            }

            if (! auth()->user()->hasRole($submitAsRole)) {
                throw new \Exception('You are not allowed to submit as this role');
            }

            $submission->participants()->create([
                'user_id' => $submission->user_id,
                'role_id' => $submitAsRole->getKey(),
            ]);
        } catch (\Throwable $th) {
            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();

            return;
        }

        return redirect()->to(SubmissionResource::getUrl('view', [$submission->id]));
    }
}
