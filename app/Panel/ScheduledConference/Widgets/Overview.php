<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Models\Enums\RegistrationPaymentState;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Registration;
use App\Models\RegistrationType;
use App\Models\Submission;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\View\Compilers\BladeCompiler;

class Overview extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make(__('general.submitted_papers'), Submission::count())
                ->icon('heroicon-o-document-text')
                ->description(__('general.total_number_of_papers_submitted')),
            Stat::make(__('general.accepted_papers'), Submission::query()
                ->whereIn('status', [
                    SubmissionStatus::OnReview,
                    SubmissionStatus::OnPresentation,
                    SubmissionStatus::Editing,
                    SubmissionStatus::Published,
                ])
                ->where('published_at', '>=', now()->subMonth())
                ->count())
                ->description(__('general.accepted_paper_in_the_last_30_days')),
            Stat::make(__('general.new_authors'), User::query()
                ->where('created_at', '>=', now()->subMonth())
                ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Author->value))
                ->count())
                ->icon('heroicon-o-user-group')
                ->description(__('general.new_authors_in_the_last_30_days')),
            Stat::make(__('general.pending_submission_payment'), Submission::query()
                ->where('stage', SubmissionStage::Payment)
                ->orWhere('status', SubmissionStatus::OnPayment)
                ->count())
                ->description(new HtmlString(BladeCompiler::render(<<<BLADE
                    <p>
                        {{ __('general.total_pending_submission_payment') }}
                        <a href="{{ App\Panel\ScheduledConference\Resources\SubmissionResource::getUrl('index') }}" style="color: rgb(59 130 246);"><x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-4 w-4 mx-1 inline-block"/></a>
                    </p>
                BLADE))),
        ];
    }
}
