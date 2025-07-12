<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Registration;
use App\Models\RegistrationForm;
use App\Models\RegistrationType;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ParticipantRegistration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'panel.scheduledConference.pages.participant-register';

    protected static ?int $navigationSort = 99;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'given_name' => auth()->user()?->given_name,
            'family_name' => auth()->user()?->family_name,
            'email' => auth()->user()?->email,
            'affiliation' => auth()->user()?->getMeta('affiliation')
        ]);
    }

    public static function canAccess(): bool
    {
        return app()->getCurrentScheduledConference()->isRegistrationOpen() && !auth()->user()?->isRegisteredAsParticipant();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return !auth()->user()?->isRegisteredAsParticipant() && app()->getCurrentScheduledConference()->isRegistrationOpen();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'coverImageUrl' => app()->getCurrentScheduledConference()->getFirstMediaUrl('registration_cover'),
            'registrationFormHeader' => app()->getCurrentScheduledConference()->getMeta('registration_form_header') ? new HtmlString(app()->getCurrentScheduledConference()->getMeta('registration_form_header')) : null,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->operation('create')
            ->schema([
                Section::make()
                    ->columns(1)
                    ->schema(RegistrationForm::getFormSchema()),
            ])
            ->statePath('formData');
    }

    public function submit()
    {
        $formData = $this->form->getState();

        try {
            DB::beginTransaction();

            $registrationType = RegistrationType::findOrFail($formData['type']);
            $scheduledConference = app()->getCurrentScheduledConference();
            $currentUser = auth()->user();

            $registration = new Registration();
            $registration->given_name   = $formData['given_name'];
            $registration->family_name  = $formData['family_name'];
            $registration->email        = $currentUser->email;
            $registration->type         = $registrationType->name;
            $registration->cost         = $registrationType->cost;
            $registration->currency     = $registrationType->currency;
            $registration->number       = $scheduledConference->getMeta('invoice_prefix_number') . str_pad($scheduledConference->getMeta('invoice_number'), 3, '0', STR_PAD_LEFT);

            $registration->save();

            if (array_key_exists('meta', $formData)) {
                $registration->setManyMeta($formData['meta']);
            };

            $scheduledConference->setMeta('invoice_number', $scheduledConference->getMeta('invoice_number') + 1);

            DB::commit();
        } catch (\Throwable $th) {
            Notification::make()
                ->danger()
                ->title($th->getMessage())
                ->send();

            DB::rollBack();
            throw $th;
        }

        Notification::make()
            ->success()
            ->title(__('general.saved'))
            ->send();

        redirect()->to(Dashboard::getUrl());
    }
}
