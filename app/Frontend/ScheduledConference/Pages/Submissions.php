<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Track;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Contracts\Support\Htmlable;

class Submissions extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.submissions';

    public function mount()
    {
        //
    }

    public function getTitle(): string|Htmlable
    {
        return __('general.submissions');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.submissions'),
        ];
    }

    protected function getViewData(): array
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        return [
            'isOpen' => CreateSubmission::isAcceptingSubmissions(auth()->user()),
            'authorGuidelines' => $this->contentOrNull($scheduledConference->translated('author_guidelines')),
            'submissionChecklist' => $this->contentOrNull($scheduledConference->translated('submission_checklist')),
            'privacyStatement' => $this->contentOrNull($scheduledConference->translated('privacy_statement')),
            'tracks' => Track::query()
                ->active()
                ->ordered()
                ->with(['meta', 'translate'])
                ->get()
                ->reject(fn (Track $track) => ContentLocales::isBlank($track->translated('policy')))
                ->values(),
        ];
    }

    protected function contentOrNull(mixed $value): ?string
    {
        return ContentLocales::isBlank($value) ? null : (string) $value;
    }
}
