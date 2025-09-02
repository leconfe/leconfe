<?php

namespace App\Utils\UpgradeSchemas;

use App\Facades\Setting;
use App\Models\Announcement;
use App\Models\Author;
use App\Models\AuthorRole;
use App\Models\Committee;
use App\Models\CommitteeRole;
use App\Models\Conference;
use App\Models\Media;
use App\Models\NavigationMenuItem;
use App\Models\ScheduledConference;
use App\Models\Site;
use App\Models\Speaker;
use App\Models\SpeakerRole;
use App\Models\StaticPage;
use App\Models\Submission;
use App\Models\SubmissionFileType;
use App\Models\Timeline;
use App\Models\Topic;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Lazy;

class Upgrade140Beta1 extends UpgradeBase
{
    public function run(): void
    {
        $defaultLocale = Setting::get('default_language') ?? config('app.locale', 'en');

        DB::beginTransaction();
        try {

            // ADMINISTRATION PANEL
            $this->convertSiteSetupMeta($defaultLocale);
            $this->convertNavigationMenuItem($defaultLocale);
            $this->convertStaticPageMeta($defaultLocale);
            $this->convertConferenceMeta($defaultLocale);
            $this->convertProfileMeta($defaultLocale);

            // CONFERENCE PANEL
            $this->convertScheduledConferenceMeta($defaultLocale);

            // SCHEDULED CONFERENCE PANEL
            $this->convertSubmissionDetailMeta($defaultLocale);
            $this->convertAuthorMeta($defaultLocale);
            $this->convertWorkflowSubmissionComponentsMeta($defaultLocale);
            $this->convertWorkflowAuthorRolesMeta($defaultLocale);
            $this->convertWorkflowSubmissionTrackMeta($defaultLocale);
            $this->convertWorkflowSubmissionTopicMeta($defaultLocale);
            $this->convertWorkflowPublisherLibraryMeta($defaultLocale);
            $this->convertAnnouncementMeta($defaultLocale);
            $this->convertCommitteesMeta($defaultLocale);
            $this->convertCommitteesRolesMeta($defaultLocale);
            $this->convertSpeakerMeta($defaultLocale);
            $this->convertSpeakerRolesMeta($defaultLocale);
            $this->convertTimelinesMeta($defaultLocale);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function convertSiteSetupMeta(string $locale)
    {
        Site::withoutGlobalScopes()->lazy()->each(function (Site $site) use ($locale) {
            $originalName = $site->getMeta('name') ?? null;
            if (filled($originalName)) {
                $site->setMeta('name', [$locale => $originalName]);

                if ($site->isDirty('meta')) {
                    $site->saveQuietly();
                }
            }
        });
    }

    // ADMINISTRATION PANEL 
    protected function convertNavigationMenuItem(string $locale)
    {
        NavigationMenuItem::withoutGlobalScopes()->lazy()->each(function (NavigationMenuItem $item) use ($locale) {
            $originalLabel = $item->label ?? null;
            if (filled($originalLabel)) {
                $item->setMeta('label', [$locale => $originalLabel]);

                if ($item->isDirty('meta')) {
                    $item->saveQuietly();
                }
            }
        });
    }

    protected function convertStaticPageMeta(string $locale)
    {
        StaticPage::withoutGlobalScopes()->lazy()->each(function (StaticPage $page) use ($locale) {
            $originalTitle = $page->title ?? null;
            if (filled($originalTitle)) {
                $page->setMeta('title', [$locale => $originalTitle]);

                if ($page->isDirty('meta')) {
                    $page->saveQuietly();
                }
            }
        });
    }

    protected function convertConferenceMeta(string $locale)
    {
        Conference::withoutGlobalScopes()->lazy()->each(function (Conference $conference) use ($locale) {
            $originalName = $conference->name ?? null;
            if (filled($originalName)) {
                $conference->setMeta('name', [$locale => $originalName]);

                if ($conference->isDirty('meta')) {
                    $conference->saveQuietly();
                }
            }
        });
    }

    protected function convertProfileMeta(string $locale)
    {
        User::withoutGlobalScopes()->lazy()->each(function (User $user) use ($locale) {
            // given name
            $originalGivenNameUser = $user->given_name ?? null;
            if (filled($originalGivenNameUser)) {
                $user->setMeta('given_name', [$locale => $originalGivenNameUser]);
            }

            // family name
            $originalFamilyNameUser = $user->family_name ?? null;
            if (filled($originalFamilyNameUser)) {
                $user->setMeta('family_name', [$locale => $originalFamilyNameUser]);
            }

            // public name
            $originalPublicNameUser = $user->public_name ?? null;
            if (filled($originalPublicNameUser)) {
                $user->setMeta('public_name', [$locale => $originalPublicNameUser]);
            }

            // simpan jika ada perubahan pada meta
            if ($user->isDirty('meta')) {
                $user->saveQuietly();
            }
        });
    }

    // CONFERENCE PANEL
    protected function convertScheduledConferenceMeta(string $locale)
    {
        ScheduledConference::withoutGlobalScopes()->lazy()->each(function (ScheduledConference $scheduledConference) use ($locale) {
            $originalTitle = $scheduledConference->title ?? null;
            if (filled($originalTitle)) {
                $scheduledConference->setMeta('title', [$locale => $originalTitle]);

                if ($scheduledConference->isDirty('meta')) {
                    $scheduledConference->saveQuietly();
                }
            }
        });
    }

    // SCHEDULED CONFERENCE PANEL

    protected function convertSubmissionDetailMeta(string $locale)
    {
        Submission::withoutGlobalScopes()->lazy()->each(function (Submission $submission) use ($locale) {
            $originalTitle = $submission->getMeta('title') ?? null;
            if (filled($originalTitle)) {
                $submission->setMeta('title', [$locale => $originalTitle]);
            }

            $originalAbstract = $submission->getMeta('abstract') ?? null;
            if (filled($originalAbstract)) {
                $submission->setMeta('abstract', [$locale => $originalAbstract]);
            }

            $originalKeywords = $submission->getMeta('keywords') ?? null;
            if (filled($originalKeywords) && is_array($originalKeywords)) {
                $submission->setMeta('keywords', [$locale => $originalKeywords]);
            }

            // simpan jika ada perubahan pada meta
            if ($submission->isDirty('meta')) {
                $submission->saveQuietly();
            }
        });
    }

    protected function convertAuthorMeta(string $locale)
    {
        Author::withoutGlobalScopes()->lazy()->each(function (Author $author) use ($locale) {
            $originalGivenNameAuthor = $author->given_name ?? null;
            if (filled($originalGivenNameAuthor)) {
                $author->setMeta('given_name', [$locale => $originalGivenNameAuthor]);
            }

            $originalFamilyNameAuthor = $author->family_name ?? null;
            if (filled($originalFamilyNameAuthor)) {
                $author->setMeta('family_name', [$locale => $originalFamilyNameAuthor]);
            }

            $originalPublicNameAuthor = $author->public_name ?? null;
            if (filled($originalPublicNameAuthor)) {
                $author->setMeta('public_name', [$locale => $originalPublicNameAuthor]);
            }

            if ($author->isDirty('meta')) {
                $author->saveQuietly();
            }
        });
    }

    protected function convertWorkflowSubmissionComponentsMeta(string $locale)
    {
        SubmissionFileType::withoutGlobalScopes()->lazy()->each(function (SubmissionFileType $type) use ($locale) {
            $originalNameSubmissionFileType = $type->name ?? null;
            if (filled($originalNameSubmissionFileType)) {
                $type->setMeta('name', [$locale => $originalNameSubmissionFileType]);

                if ($type->isDirty('meta')) {
                    $type->saveQuietly();
                }
            }
        });
    }

    protected function convertWorkflowAuthorRolesMeta(string $locale)
    {
        AuthorRole::withoutGlobalScopes()->lazy()->each(function (AuthorRole $authorRole) use ($locale) {
            $originalNameAuthorRole = $authorRole->name ?? null;
            if (filled($originalNameAuthorRole)) {
                $authorRole->setMeta('name', [$locale => $originalNameAuthorRole]);

                if ($authorRole->isDirty('meta')) {
                    $authorRole->saveQuietly();
                }
            }
        });
    }

    protected function convertWorkflowSubmissionTrackMeta(string $locale)
    {
        Track::withoutGlobalScopes()->lazy()->each(function (Track $track) use ($locale) {
            $originalNameTrack = $track->title ?? null;
            if (filled($originalNameTrack)) {
                $track->setMeta('title', [$locale => $originalNameTrack]);

                if ($track->isDirty('meta')) {
                    $track->saveQuietly();
                }
            }
        });
    }

    protected function convertWorkflowSubmissionTopicMeta(string $locale)
    {
        Topic::withoutGlobalScopes()->lazy()->each(function (Topic $topic) use ($locale) {
            $originalNameTopic = $topic->name ?? null;
            if (filled($originalNameTopic)) {
                $topic->setMeta('name', [$locale => $originalNameTopic]);

                if ($topic->isDirty('meta')) {
                    $topic->saveQuietly();
                }
            }
        });
    }

    protected function convertWorkflowPublisherLibraryMeta(string $locale)
    {
        Media::withoutGlobalScopes()
            ->where('model_type', ScheduledConference::class)
            ->where('collection_name', 'publisher-library')->lazy()->each(function (Media $media) use ($locale) {

                $originalName = $media->name ?? null;
                if (filled($originalName)) {
                    $media->setMeta('name', [$locale => $originalName]);

                    if ($media->isDirty('meta')) {
                        $media->saveQuietly();
                    }
                }
            });
    }

    protected function convertAnnouncementMeta(string $locale)
    {
        Announcement::withoutGlobalScopes()->lazy()->each(function (Announcement $announcement) use ($locale) {
            $originalNameAnnouncement = $announcement->title ?? null;
            if (filled($originalNameAnnouncement)) {
                $announcement->setMeta('title', [$locale => $originalNameAnnouncement]);

                if ($announcement->isDirty('meta')) {
                    $announcement->saveQuietly();
                }
            }
        });
    }

    protected function convertCommitteesMeta(string $locale)
    {
        Committee::withoutGlobalScopes()->lazy()->each(function (Committee $committee) use ($locale) {
            $originalGivenNameCommittee = $committee->given_name ?? null;
            if (filled($originalGivenNameCommittee)) {
                $committee->setMeta('given_name', [$locale => $originalGivenNameCommittee]);
            }

            $originalFamilyNameCommittee = $committee->family_name ?? null;
            if (filled($originalFamilyNameCommittee)) {
                $committee->setMeta('family_name', [$locale => $originalFamilyNameCommittee]);
            }

            $originalPublicNameCommittee = $committee->public_name ?? null;
            if (filled($originalPublicNameCommittee)) {
                $committee->setMeta('public_name', [$locale => $originalPublicNameCommittee]);
            }

            if ($committee->isDirty('meta')) {
                $committee->saveQuietly();
            }
        });
    }

    protected function convertCommitteesRolesMeta(string $locale)
    {
        CommitteeRole::withoutGlobalScopes()->lazy()->each(function (CommitteeRole $committeeRole) use ($locale) {
            $originalPositionCommitteeRole = $committeeRole->name ?? null;
            if (filled($originalPositionCommitteeRole)) {
                $committeeRole->setMeta('name', [$locale => $originalPositionCommitteeRole]);

                if ($committeeRole->isDirty('meta')) {
                    $committeeRole->saveQuietly();
                }
            }
            $committeeRole->saveQuietly();
        });
    }

    protected function convertSpeakerMeta(string $locale)
    {
        Speaker::withoutGlobalScopes()->lazy()->each(function (Speaker $speaker) use ($locale) {
            $originalGivenNameSpeaker = $speaker->given_name ?? null;
            if (filled($originalGivenNameSpeaker)) {
                $speaker->setMeta('given_name', [$locale => $originalGivenNameSpeaker]);
            }

            $originalFamilyNameSpeaker = $speaker->family_name ?? null;
            if (filled($originalFamilyNameSpeaker)) {
                $speaker->setMeta('family_name', [$locale => $originalFamilyNameSpeaker]);
            }

            $originalPublicNameSpeaker = $speaker->public_name ?? null;
            if (filled($originalPublicNameSpeaker)) {
                $speaker->setMeta('public_name', [$locale => $originalPublicNameSpeaker]);
            }

            if ($speaker->isDirty('meta')) {
                $speaker->saveQuietly();
            }
        });
    }

    protected function convertSpeakerRolesMeta(string $locale)
    {
        SpeakerRole::withoutGlobalScopes()->lazy()->each(function (SpeakerRole $speakerRole) use ($locale) {
            $originalPositionSpeakerRole = $speakerRole->name ?? null;
            if (filled($originalPositionSpeakerRole)) {
                $speakerRole->setMeta('name', [$locale => $originalPositionSpeakerRole]);

                if ($speakerRole->isDirty('meta')) {
                    $speakerRole->saveQuietly();
                }
            }
        });
    }

    protected function convertTimelinesMeta(string $locale)
    {
        Timeline::withoutGlobalScopes()->lazy()->each(function (Timeline $timeline) use ($locale) {
            $originalNameTimeline = $timeline->name ?? null;

            if (filled($originalNameTimeline)) {
                $timeline->setMeta('name', [$locale => $originalNameTimeline]);

                if ($timeline->isDirty('meta')) {
                    $timeline->saveQuietly();
                }
            }
        });
    }
}
