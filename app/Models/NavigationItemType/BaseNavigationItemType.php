<?php

namespace App\Models\NavigationItemType;

use App\Models\NavigationMenuItem;

abstract class BaseNavigationItemType
{
    abstract public static function getId(): string;

    abstract public static function getLabel(): string;

    public static function getDescription(): ?string
    {
        return null;
    }

    public static function getIsDisplayed(NavigationMenuItem $navigationMenuItem): bool
    {
        return true;
    }

    public static function getAdditionalForm(): array
    {
        return [];
    }

    public static function getAdditionalFormData(NavigationMenuItem $navigationMenuItem): array
    {
        return [];
    }

    public static function getUrl(NavigationMenuItem $navigationMenuItem): string
    {
        return '#';
    }
}
