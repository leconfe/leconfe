<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SubmissionsTableWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {

        return SubmissionResource::table($table)
            ->heading(__('general.my_submissions'))
            ->query(
                SubmissionResource::getEloquentQuery()
                    ->whereHas('participants', fn (Builder $query) => $query->where('user_id', auth()->id()))
                    ->orWhereHas('reviews', fn (Builder $query) => $query->where('user_id', auth()->id()))
            );
    }
}
