<?php

namespace App\Panel\Administration\Resources\StaticPageResource\Pages;

use App\Managers\StaticPageBlockManager;
use App\Models\StaticPage;
use App\Panel\Administration\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaticPages extends ListRecords
{
    protected static string $resource = StaticPageResource::class;

    public function mount() : void
    {
        $staticPage = StaticPage::first();
        // dd($staticPage->getAllMeta());
        // $blockManager = app(StaticPageBlockManager::class);
        // collect($staticPage->getMeta('blocks'))
        //     ->map(fn($block) => $blockManager->initBlock($block['type'], $block['data']))
        //     ->map(fn($block) => $block->toHtml())
        //     ->dd();


    }

    public static function getNavigationLabel(): string
    {
        return "Manage Pages";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
