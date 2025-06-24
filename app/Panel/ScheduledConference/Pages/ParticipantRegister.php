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
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ParticipantRegister extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'panel.scheduledConference.pages.participant-register';

    // protected static ?int $navigationSort = 99;

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

    public function form(Form $form): Form
    {
        return $form
            ->extraAttributes([
                'class' => 'max-w-3xl'
            ])
            ->schema([
                Section::make()
                    ->columns(1)
                    ->schema(RegistrationForm::getFormSchema()),
                 Actions::make([
                    Action::make('save')
                        ->label(__('general.submit'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
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

                                $registration->setManyMeta($formData);

                                DB::commit();
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->failureNotificationTitle($th->getMessage());
                                $action->sendFailureNotification();
                                DB::rollBack();
                                throw $th;
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }
}
