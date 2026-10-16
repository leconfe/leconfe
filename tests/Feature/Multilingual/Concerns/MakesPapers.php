<?php

namespace Tests\Feature\Multilingual\Concerns;

use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;

trait MakesPapers
{
    protected function makeAuthor(string $email = 'author@example.test'): User
    {
        return User::query()->create([
            'given_name' => 'Ayu',
            'family_name' => 'Lestari',
            'email' => $email,
            'password' => 'password123456',
        ]);
    }

    protected function makeTrack(): Track
    {
        return Track::query()->firstOrCreate(['abbreviation' => 'GEN'], ['title' => 'General']);
    }

    /**
     * A submission written in $language. $metadata maps a key (title, subtitle, abstract,
     * keywords) to its values per locale. The primary-language value is also stored in
     * the legacy meta, as the submission forms do.
     *
     * @param  array<string, array<string, mixed>>  $metadata
     */
    protected function makePaper(
        User $author,
        string $language,
        array $metadata,
        SubmissionStage $stage = SubmissionStage::Wizard,
        SubmissionStatus $status = SubmissionStatus::Incomplete,
        ?Proceeding $proceeding = null,
    ): Submission {
        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => app()->getCurrentConferenceId(),
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
            'track_id' => $this->makeTrack()->getKey(),
            'proceeding_id' => $proceeding?->getKey(),
            'stage' => $stage,
            'status' => $status,
            'published_at' => $status === SubmissionStatus::Published ? now()->subDay() : null,
        ]);

        $submission->setMeta('language', $language);

        foreach ($metadata as $key => $values) {
            foreach ($values as $locale => $value) {
                $submission->setTranslation($key, $locale, $value);
            }
        }

        return $submission->refresh();
    }

    protected function makeProceeding(string $title = 'Proceedings 2026'): Proceeding
    {
        return Proceeding::query()->create([
            'title' => $title,
            'volume' => 1,
            'number' => 2,
            'year' => 2026,
            'published' => true,
            'published_at' => now()->subWeek(),
        ]);
    }

    protected function paperUrl(Conference $conference, Submission $submission): string
    {
        return route('livewirePageGroup.conference.pages.paper', [
            'conference' => $conference->path,
            'submission' => $submission->getKey(),
        ]);
    }

    protected function visitPaper(Conference $conference, Submission $submission, string $locale)
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get($this->paperUrl($conference, $submission))
            ->assertOk();
    }
}
