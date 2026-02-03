<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Managers\PaymentManager;
use App\Models\Enums\UserRole;
use App\Models\Participant;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Models\Presentation;
use App\Models\User;
use App\Notifications\ParticipantPayment;
use App\Notifications\ParticipantRegistered;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Squire\Models\Country;

class Presentations extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string $view = 'panel.scheduledConference.pages.presentations';

    protected static ?int $navigationSort = 99;

    public ?array $formData = [];

    public function mount(): void
    {
       
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $presentations = Presentation::query()
            ->with([
                'meta',
                'media',
                'submission' => ['meta', 'track'],
            ])
            ->isFinal()
            ->get();

        return [
           'presentations' => $presentations
        ];
    }
}
