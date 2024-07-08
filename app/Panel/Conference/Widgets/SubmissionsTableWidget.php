<?php

namespace App\Panel\Conference\Widgets;

use App\Models\Submission;
use App\Panel\Conference\Resources\SubmissionResource;
use App\Panel\Conference\Resources\SubmissionResource\Pages\ManageSubmissions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SubmissionsTableWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $submissionQuery = ManageSubmissions::generateQueryByCurrentUser('My Queue');


        return SubmissionResource::table($table)
            ->heading(__('translation.dashboard.headingMySubmissions'))
            ->query($submissionQuery);
    }
}
