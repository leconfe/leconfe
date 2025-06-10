<?php

namespace App\Panel\Administration\Resources;

use App\Facades\StaticPageBlockFacade;
use App\Managers\StaticPageBlockManager;
use App\Panel\Administration\Resources\StaticPageResource\Pages;
use App\Models\StaticPage;
use App\Panel\Administration\Resources\StaticPageResource\Pages\EditStaticPage;
use App\Panel\Administration\Resources\StaticPageResource\Pages\HomePage;
use App\Panel\Administration\Resources\StaticPageResource\Pages\ListStaticPages;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action as ActionForm;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Validation\Rules\Unique;

class StaticPageResource extends Resource
{
    protected static ?string $model = StaticPage::class;

    public static function getEloquentQuery(): EloquentBuilder
    {
        return parent::getEloquentQuery()->isDefault(false);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->extraAttributes(['class' => 'max-w-3xl'])
            ->columns(1)
            ->schema([
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('general.title'))
                            ->required(),
                        TextInput::make('slug')
                            ->label(__('general.slug'))
                            ->alphaDash()
                            ->required()
                            ->helperText(__('general.slug_helper'))
                            ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) {
                                return $rule
                                    ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId());
                            }),
                    ]),
                StaticPageBlockFacade::getBuilder(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('url')
                    ->getStateUsing(fn($record) => $record->getUrl())
                    ->url(fn($record) => $record->getUrl())
                    ->color('primary')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaticPages::route('/'),
            'home' => Pages\HomePage::route('/home'),
            'create' => Pages\CreateStaticPage::route('/create'),
            'edit' => Pages\EditStaticPage::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn() => request()->routeIs(ListStaticPages::getRouteName()))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }

    public static function registerNavigationItems(): void
    {
        if (filled(static::getCluster())) {
            return;
        }

        if (! static::shouldRegisterNavigation()) {
            return;
        }

        if (! static::canAccess()) {
            return;
        }

        $pages = StaticPage::isDefault(false)
            ->limit(10)
            ->get()
            ->map(
                fn(StaticPage $page) => NavigationItem::make($page->title)
                    ->url(EditStaticPage::getUrl(['record' => $page]))
                    ->group('Pages')
                    ->isActiveWhen(fn() => request()->fullUrlIs(EditStaticPage::getUrl(['record' => $page])))
            );

        Filament::getCurrentPanel()
            ->navigationItems([
                ...HomePage::getNavigationItems(),
                ...$pages->toArray(),
                ...ListStaticPages::getNavigationItems(),
            ])
            ->navigationGroups([
                NavigationGroup::make('Pages'),
            ]);
    }
}
