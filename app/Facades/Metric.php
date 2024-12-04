<?php

namespace App\Facades;

use App\Managers\MetricManager;
use Illuminate\Support\Facades\Facade;

class Metric extends Facade
{
    protected static function getFacadeAccessor()
    {
        return MetricManager::class;
    }
}
