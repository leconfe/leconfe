<?php

namespace App\Panel\Conference\Widgets;

use App\Models\AuthorRole;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Submission;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Overview extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;
    
    protected function getStats(): array
    {
        return [
            Stat::make(__('translation.dashboard.titleSubmittedSubmissions'), Submission::count())
                ->icon('heroicon-o-document-text')
                ->description(__('translation.dashboard.descriptionTotalnumberofSubmissions')),
            Stat::make(__('translation.dashboard.titlePublishedSubmission'), Submission::query()
                ->where('status', SubmissionStatus::Published)
                ->where('published_at', '>=', now()->subMonth())
                ->count())
                ->description(__('translation.dashboard.descriptionPublishedTheLast30Days')),
            Stat::make(__('translation.dashboard.titleNewAuthors'), User::query()
                ->where('created_at', '>=', now()->subMonth())
                ->whereHas('roles', fn($query) => $query->where('name', UserRole::Author->value))
                ->count())
                ->icon('heroicon-o-user-group')
                ->description(__('translation.dashboard.descriptionNewAuthorsIn')),
        ];
    }
}
