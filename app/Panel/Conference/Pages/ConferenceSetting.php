<?php

namespace App\Panel\Conference\Pages;

use App\Panel\Conference\Livewire\MastHeadSetting;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class ConferenceSetting extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists;

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationIcon = 'heroicon-s-window';

    protected static string $view = 'panel.conference.pages.conference';

    public static function getNavigationLabel(): string
    {
        return __('general.conference');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.conference_settings');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    public function mount(): void
    {
        $this->authorize('update', App::getCurrentConference());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('update', App::getCurrentConference());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Livewire::make(MastHeadSetting::class),
            ]);
    }
}
