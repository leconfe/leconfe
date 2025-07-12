<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Facades\Setting;
use App\Models\Registration;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\Invoice;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets\Widget;
use Squire\Models\Country;
use Squire\Models\Currency;

class RegistrationInfo extends Widget implements HasInfolists, HasForms
{
    use InteractsWithInfolists, InteractsWithForms;

    protected static string $view = 'panel.scheduledConference.widgets.registration-info';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public ?Registration $record;

    public function mount()
    {
        $this->record = Registration::query()
            ->where('email', auth()->user()->email)
            ->first();
    }

    public static function canView(): bool
    {
        if(auth()->user()?->can('update', app()->getCurrentScheduledConference())){
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'isRegiteredAsParticipant' => filled($this->record),
            'registerAsParticipantUrl' => ParticipantRegistration::getUrl(),
            // 'registrationDetailUrl' => RegistrationDetail::getUrl(),
            'scheduledConference' => app()->getCurrentScheduledConference(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $this->record->detailInfolist($infolist);
    }
}
