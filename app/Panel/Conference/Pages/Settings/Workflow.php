<?php

namespace App\Panel\Conference\Pages\Settings;

use App\Infolists\Components\LivewireEntry;
use App\Infolists\Components\VerticalTabs\Tab;
use App\Infolists\Components\VerticalTabs\Tabs;
use App\Panel\Conference\Livewire\Tables\AuthorRoleTable;
use App\Panel\Conference\Livewire\Workflows\AbstractSetting;
use App\Panel\Conference\Livewire\Workflows\EditingSetting;
use App\Panel\Conference\Livewire\Workflows\Payment\Tables\SubmissionPaymentItemTable;
use App\Panel\Conference\Livewire\Workflows\PaymentSetting;
use App\Panel\Conference\Livewire\Workflows\PeerReview\Forms\Guidelines;
use App\Panel\Conference\Livewire\Workflows\PeerReviewSetting;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Tabs as HorizontalTabs;
use Filament\Infolists\Components\Tabs\Tab as HorizontalTab;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;


class Workflow extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists;

    protected static ?int $navigationSort = 1;

    protected static string $view = 'panel.conference.pages.workflow';

    protected static ?string $navigationIcon = 'heroicon-o-window';

    // protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string
    {
        return __('translation.pluginResource.navigationGroupTitle');
    }

    // public static function getModelLabel(): string
    // {
    //     return __('translation.workflow.getModelLabelWorkflow');
    // }

    public static function getNavigationLabel(): string
    {
        return __('translation.workflow.getModelLabelWorkflow');
    }

    public function getHeading(): string|Htmlable
    {
        return __('translation.workflow.getModelLabelWorkflow');
    }

    public function mount()
    {
        //
    }

    public function booted(): void
    {
        abort_if(! static::canView(), 403);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canView() && static::$shouldRegisterNavigation;
    }

    public static function canView(): bool
    {
        return Filament::auth()->user()->can('Workflow:update');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Tabs::make()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Call for Abstract')
                        ->label(__('translation.workflow.tabCallforAbstract'))
                        ->icon('iconpark-documentfolder-o')
                        ->schema([
                            HorizontalTabs::make()
                                ->tabs([
                                    HorizontalTab::make(__('translation.workflow.horizontalTabGeneral'))
                                        ->icon('iconpark-documentfolder-o')
                                        ->schema([
                                            LivewireEntry::make('abstract-setting')
                                                ->livewire(AbstractSetting::class),
                                        ]),
                                ]),
                        ]),
                    Tab::make('Payment')
                        ->label(__('translation.workflow.tabCallforPayment'))
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            HorizontalTabs::make()
                                ->tabs([
                                    HorizontalTab::make(__('translation.workflow.horizontalTabPayment'))
                                        ->schema([
                                            LivewireEntry::make('payment-setting')
                                                ->livewire(PaymentSetting::class),
                                        ]),
                                    HorizontalTab::make(__('translation.workflow.horizontalTabSubmissionPaymentItems'))
                                        ->schema([
                                            LivewireEntry::make('payment-items')
                                                ->livewire(SubmissionPaymentItemTable::class),
                                        ]),
                                ]),

                        ]),
                    Tab::make('Peer Review')
                        ->label(__('translation.workflow.tabCallforPeerReview'))
                        ->icon('iconpark-search-o')
                        ->schema([
                            HorizontalTabs::make()
                                ->tabs([
                                    HorizontalTab::make(__('translation.workflow.horizontalTabPeerReview'))
                                        ->icon('iconpark-documentfolder-o')
                                        ->schema([
                                            LivewireEntry::make('peer-review-setting')
                                                ->livewire(PeerReviewSetting::class)
                                                ->lazy(),
                                        ]),
                                    HorizontalTab::make(__('translation.workflow.horizontalTabPeerReviewGuidelines'))
                                        ->icon('iconpark-docsuccess-o')
                                        ->schema([
                                            LivewireEntry::make('peer-review-setting')
                                                ->livewire(Guidelines::class)
                                                ->lazy(),
                                        ]),
                                    // HorizontalTab::make("Review Forms")
                                    //     ->icon("iconpark-formone-o")
                                    //     ->schema([
                                    //         LivewireEntry::make('peer-review-form-templates')
                                    //             ->livewire(FormTemplate::class)
                                    //             ->lazy()
                                    //     ])
                                ]),
                        ]),
                    Tab::make('Editing')
                        ->label(__('translation.workflow.tabCallforEditing'))
                        ->icon('iconpark-paperclip')
                        ->schema([
                            HorizontalTabs::make()
                                ->tabs([
                                    HorizontalTab::make(__('translation.workflow.horizontalTabEditing'))
                                        ->icon('iconpark-documentfolder-o')
                                        ->schema([
                                            LivewireEntry::make('editing-setting')
                                                ->livewire(EditingSetting::class)
                                                ->lazy(),
                                        ]),
                                ]),
                        ]),
                    Tab::make('Advanced')
                        ->label(__('translation.workflow.tabCallforAdvanced'))
                        ->icon('heroicon-o-bookmark-square')
                        ->schema([
                            HorizontalTabs::make()
                                ->tabs([
                                    HorizontalTab::make(__('translation.workflow.horizontalTabAuthorRoles'))
                                        ->icon('heroicon-o-users')
                                        ->extraAttributes(['class' => '!p-0'])
                                        ->schema([
                                            LivewireEntry::make('author-roles')
                                                ->livewire(AuthorRoleTable::class),
                                        ]),
                                ]),
                        ]),
                ])
                ->maxWidth('full'),
        ]);
    }
}
