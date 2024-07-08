<?php

namespace App\Panel\Conference\Resources;

use App\Forms\Components\TagSuggestions;
use App\Models\Enums\ContentType;
use App\Models\StaticPage;
use App\Models\StaticPageTag;
use App\Panel\Conference\Resources\StaticPageResource\Pages;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class StaticPageResource extends Resource
{
    protected static ?string $model = StaticPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    // protected static ?string $navigationGroup = 'Conferences';

    public static function getModelLabel(): string
    {
        return __('translation.staticPageResource.staticPageResourceGetModelLabel');
    }

    public static function getNavigationGroup(): string
    {
        return __('translation.announcementResource.announcementResourceNavigationGroup');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(12)
                    ->schema([
                        Section::make()
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('translation.staticPageResource.staticPageResourceLabelTitle'))
                                    ->lazy()
                                    ->helperText(function ($state, ?StaticPage $record) {

                                        if (! $record) {
                                            return;
                                        }

                                        $slug = Str::slug($state);
                                        $currentSlug = Str::slug(substr($slug, 0, 50)); // if it has(-) at the end
                                        $count = 1;

                                        switch (true) {
                                            case $record:
                                                while (true) {
                                                    $staticPage = StaticPage::where('slug', $currentSlug)->first();
                                                    if ($staticPage) {
                                                        if ($staticPage->id != $record->id) {
                                                            $currentSlug = "{$slug}-{$count}";
                                                            $count++;
                                                        } else {
                                                            break;
                                                        }
                                                    } else {
                                                        break;
                                                    }
                                                }
                                                break;

                                            default:
                                                while (true) {
                                                    $staticPage = StaticPage::where('slug', $currentSlug)->first();
                                                    // dd($staticPage);
                                                    if ($staticPage) {
                                                        $currentSlug = "{$slug}-{$count}";
                                                        $count++;
                                                    } else {
                                                        break;
                                                    }
                                                }
                                                break;
                                        }

                                        $route = $record->getUrl();

                                        return new HtmlString("
                                        <p>" . __('translation.staticPageResource.staticPageResourceHtmlString') . "</p>
                                        <p>{$route}</p>
                                    ");
                                    })
                                    ->required(),
                                TinyEditor::make('meta.content')
                                    ->label(__('translation.staticPageResource.staticPageResourceLabelContent'))
                                    ->minHeight(600)
                                    ->helperText(__('translation.staticPageResource.staticPagehelperText')),
                            ])->columnSpan([
                                'default' => 'full',
                                'lg' => 9,
                            ]),
                        Section::make()
                            ->schema([
                                TextInput::make('author')
                                    ->default(function () {
                                        $user = auth()->user();

                                        return $user->full_name;
                                    })
                                    ->dehydrated(false)
                                    ->disabled()
                                    ->label(__('translation.staticPageResource.staticPageResourceLabelAuthor')),
                                SpatieTagsInput::make('tags')
                                    ->type(ContentType::StaticPage->value)
                                    ->afterStateUpdated(fn ($set, $state) => $set('common_tags', StaticPageTag::whereInFromString($state, ContentType::StaticPage->value)->pluck('id')->toArray()))
                                    ->reactive()
                                    ->label(__('translation.staticPageResource.staticPageResourceLabelTags')),
                                TagSuggestions::make('common_tags')
                                    ->label(__('translation.staticPageResource.staticPageResourceLabelCommonlyUsedTags'))
                                    ->helperText(
                                        fn (CheckboxList $component) => count($component->getOptions()) ? null :
                                            new HtmlString('
                                                    <div class="fi-ta-empty-state-content mx-auto grid max-w-lg justify-items-center text-center">
                                                        <div class="fi-ta-empty-state-icon-ctn mb-4 rounded-full bg-gray-100 p-3 dark:bg-gray-500/20">
                                                            <svg class="fi-ta-empty-state-icon h-6 w-6 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </div>
                                                    
                                                        <h4 class="fi-ta-empty-state-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                                        ' . __("translation.staticPageResource.staticPageResourceH4NoTags") . '
                                                        </h4>
                                                    </div>
                                    ')
                                    )
                                    ->options(StaticPageTag::withCount('staticPages')->orderBy('static_pages_count', 'desc')->limit(10)->pluck('name', 'id')->toArray())
                                    ->columns('2')
                                    ->afterStateUpdated(function ($set, $state) {
                                        if (! empty($state)) {
                                            $state = StaticPageTag::whereIn('id', $state)->get()->map(fn ($tag) => $tag->name)->toArray();
                                        }

                                        $set('tags', $state);
                                    })
                                    ->dehydrated(false)
                                    ->reactive(),
                            ])->columnSpan([
                                'default' => 'full',
                                'lg' => 3,
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('translation.staticPageResource.staticPageResourceLabelTitle'))
                    ->searchable(),
                TextColumn::make('path')
                    ->label(__('translation.staticPageResource.staticPageResourceLabelPath'))
                    ->getStateUsing(fn (StaticPage $record) => $record->getUrl()),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Action::make('preview')
                    ->label(__('translation.button.preview'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (StaticPage $record) => $record->getUrl())
                    ->color('gray'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
            'create' => Pages\CreateStaticPage::route('/create'),
            'edit' => Pages\EditStaticPage::route('/{record}/edit'),
        ];
    }
}
