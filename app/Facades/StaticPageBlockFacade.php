<?php

namespace App\Facades;

use App\Classes\Sidebar;
use App\Managers\SidebarManager;
use App\Managers\StaticPageBlockManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getBlocks()
 * @method static \Filament\Forms\Components\Builder getBuilder()
 */
class StaticPageBlockFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return StaticPageBlockManager::class;
    }
}
