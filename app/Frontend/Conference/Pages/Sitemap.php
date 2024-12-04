<?php

namespace App\Frontend\Conference\Pages;

use App\Frontend\ScheduledConference\Pages as ScheduledConferencePages;
use App\Frontend\Website\Pages\Page;
use App\Models\Announcement;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\Proceeding;
use App\Models\ScheduledConference;
use App\Models\StaticPage;
use App\Models\Submission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap as SpatieSitemap;
use Spatie\Sitemap\Tags\Url;

class Sitemap extends Page
{
    public function __invoke()
    {
        $sitemap = Cache::remember(
            'sitemap_'.app()->getCurrentConferenceId(),
            Carbon::now()->addMinutes(30),
            fn () => $this->generateSitemap(),
        );

        return response($sitemap->render(), 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function generateSitemap(): SpatieSitemap
    {
        $currentConference = app()->getCurrentConference();
        $sitemap = SpatieSitemap::create()
            ->add(
                Url::create(route(Home::getRouteName()))
                    ->setLastModificationDate($currentConference->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            )
            ->add(
                Url::create(route(AboutSystem::getRouteName()))
                    ->setLastModificationDate($currentConference->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            )
            ->add(
                Url::create(route(Login::getRouteName()))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_NEVER)
            )
            ->add(
                Url::create(route(Proceedings::getRouteName()))
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            );

        Proceeding::query()
            ->with(['conference', 'submissions' => fn ($query) => $query->with(['galleys.file.media', 'conference'])->published()])
            ->published()
            ->lazy()->each(function (Proceeding $proceeding) use ($sitemap) {
                $sitemap->add(
                    Url::create($proceeding->getUrl())
                        ->setLastModificationDate($proceeding->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                        ->setPriority(1)
                );

                $proceeding->submissions->each(function (Submission $submission) use ($sitemap) {
                    if ($submission->isPublishedOnExternal()) {
                        return;
                    }

                    $sitemap->add(
                        Url::create($submission->getUrl())
                            ->setLastModificationDate($submission->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                            ->setPriority(1)
                    );

                    foreach ($submission->galleys as $galley) {
                        if ($galley->remote_url) {
                            continue;
                        }

                        $sitemap->add(
                            Url::create($galley->getUrl())
                                ->setLastModificationDate($galley->file->media->updated_at)
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                                ->setPriority(1)
                        );
                    }

                });
            });

        StaticPage::query()
            ->lazy()
            ->each(fn ($staticPage) => $sitemap->add(
                Url::create($staticPage->getUrl())
                    ->setLastModificationDate($staticPage->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
            ));

        ScheduledConference::query()
            ->with(['conference', 'announcements.scheduledConference', 'staticPages'])
            ->whereIn('state', [ScheduledConferenceState::Current, ScheduledConferenceState::Archived, ScheduledConferenceState::Published])
            ->orderBy('date_start', 'desc')
            ->lazy()
            ->each(function (ScheduledConference $scheduledConference) use ($sitemap) {
                $sitemap->add(
                    Url::create($scheduledConference->getUrl())
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\About::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\AboutSystem::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\EditorialTeam::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\Announcements::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\Committees::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\Login::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_NEVER)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\Register::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_NEVER)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\PublisherLibrary::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_NEVER)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\PrivacyStatement::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_NEVER)
                );

                $sitemap->add(
                    Url::create(route(ScheduledConferencePages\Timelines::getRouteName('scheduledConference'), ['serie' => $scheduledConference]))
                        ->setLastModificationDate($scheduledConference->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_NEVER)
                );

                $scheduledConference->staticPages->each(fn (StaticPage $staticPage) => $sitemap->add(
                    Url::create(route(ScheduledConferencePages\StaticPage::getRouteName('scheduledConference'), ['serie' => $scheduledConference, 'staticPage' => $staticPage]))
                        ->setLastModificationDate($staticPage->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                ));

                $scheduledConference->announcements->each(fn (Announcement $announcement) => $sitemap->add(
                    Url::create($announcement->getUrl())
                        ->setLastModificationDate($announcement->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ));
            });

        return $sitemap;
    }
}
