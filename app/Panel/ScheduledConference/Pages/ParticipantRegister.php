<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\PaymentFee;
use App\Models\Registration;
use App\Models\RegistrationForm;
use App\Models\RegistrationType;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ParticipantRegister extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'panel.scheduledConference.pages.participant-register';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'given_name' => auth()->user()?->given_name,
            'family_name' => auth()->user()?->family_name,
            'email' => auth()->user()?->email,
        ]);
    }

    public static function canAccess(): bool
    {
        return !auth()->user()?->isRegisteredAsParticipant();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return !auth()->user()?->isRegisteredAsParticipant();
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

            $currentUser = auth()->user();

            $registration = new Registration();
            $registration->given_name   = $currentUser->given_name;
            $registration->family_name  = $currentUser->family_name;
            $registration->email        = $currentUser->email;
            $registration->type         = $registrationType->name;
            $registration->cost         = $registrationType->cost;
            $registration->currency     = $registrationType->currency;

            $registration->save();

            if (array_key_exists('meta', $formData)) {
                $registration->setManyMeta($formData['meta']);
            };

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
    }
}
