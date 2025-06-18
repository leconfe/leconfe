<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\PaymentFee;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ParticipantRegister extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static string $view = 'panel.scheduledConference.pages.participant-register';

    protected static ?int $navigationSort = 99;

    public ?array $formData = [];


    public function mount(): void {}

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        TextInput::make('given_name')
                            ->label(__('general.given_name'))
                            ->required(),
                        TextInput::make('family_name')
                            ->label(__('general.family_name')),
                    ]),
                TextInput::make('public_name')
                    ->helperText('Pariatur elit qui aute sint minim pariatur. Deserunt ea excepteur incididunt mollit. Commodo qui eu eiusmod ut est qui sit mollit adipisicing ex adipisicing commodo voluptate incididunt cillum. Sint fugiat adipisicing cupidatat dolor pariatur deserunt. Reprehenderit Lorem anim eu do do officia nostrud reprehenderit Lorem qui. Est consequat voluptate anim nisi labore ea ipsum amet sit sit elit cupidatat. Eiusmod aute tempor mollit nulla ea nulla culpa cillum dolore incididunt voluptate non ut proident excepteur. Veniam in in duis qui velit labore duis irure non do labore nulla aliqua.')
                    ->label(__('general.public_name')),
                TextInput::make('email')
                    ->email()
                    ->label(__('general.email'))
                    ->required(),
                Radio::make('type')
                    ->options(PaymentFee::query()->pluck('name', 'id')),
            ])
            ->statePath('formData');
    }
}
