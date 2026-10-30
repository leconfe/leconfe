<?php

namespace App\Actions\Conferences;

use App\Models\Conference;
use App\Models\Scopes\ConferenceScope;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConferenceCloneAction
{
    use AsAction;

    public function handle(array $data)
    {
        try {
            DB::beginTransaction();

            $clonedDataConference = $this->cloneConference($data);

            $this->cloneRelations($clonedDataConference);

            DB::commit();

            return $clonedDataConference;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    private function cloneConference(array $data): Conference
    {
        $clonedConferenceId = $data['conference_id'];

        $sourceConference = Conference::with([
            'meta',

            'topics' => function ($query) {
                $query->withoutGlobalScopes([ConferenceScope::class]);
            },

            'staticPages' => function ($query) {
                $query->withoutGlobalScopes([ConferenceScope::class])->with('meta');
            },
        ])->findOrFail($clonedConferenceId);

        $clonedDataConference = $sourceConference->replicate();

        $clonedDataConference->fill($data);
        $clonedDataConference->path = $this->generateUniquePath($clonedDataConference->path);
        $clonedDataConference->save();

        $sourceConference->copyTranslationsTo($clonedDataConference);

        $this->syncDefaultTranslationsOfChangedColumns($sourceConference, $clonedDataConference);

        return $clonedDataConference;
    }

    /**
     * A column given a new value for the clone (such as the name) replaces a copied
     * default-language translation, so the default language shows the new value
     * while the other languages keep the copied translations.
     */
    private function syncDefaultTranslationsOfChangedColumns(Conference $sourceConference, Conference $clonedDataConference): void
    {
        $default = app(ContentLocales::class)->default();

        foreach ($clonedDataConference->getTranslatableColumns() as $column) {
            $value = $clonedDataConference->getAttribute($column);

            if ($value === $sourceConference->getAttribute($column)) {
                continue;
            }

            if ($clonedDataConference->getStoredTranslation($column, $default) !== null) {
                $clonedDataConference->setTranslation($column, $default, $value);
            }
        }
    }

    private function cloneRelations(Conference $clonedDataConference): void
    {
        $this->cloneTopics($clonedDataConference);
        $this->cloneStaticPages($clonedDataConference);
        $this->cloneMeta($clonedDataConference);
    }

    private function cloneTopics(Conference $clonedDataConference): void
    {
        foreach ($clonedDataConference->topics as $topic) {
            $clonedTopic = $topic->replicate();
            $clonedTopic->conference_id = $clonedDataConference->id;
            $clonedTopic->save();

            $topic->copyTranslationsTo($clonedTopic);
        }
    }

    private function cloneStaticPages(Conference $clonedDataConference): void
    {
        foreach ($clonedDataConference->staticPages as $staticPage) {
            $clonedStaticPage = $staticPage->replicate();
            $clonedStaticPage->conference_id = $clonedDataConference->id;
            $clonedStaticPage->save();

            foreach ($staticPage->meta as $meta) {
                $clonedMeta = $meta->replicate();
                $clonedMeta->metable_id = $clonedStaticPage->id;
                $clonedMeta->save();
            }

            $staticPage->copyTranslationsTo($clonedStaticPage);
        }
    }

    private function cloneMeta(Conference $clonedDataConference): void
    {
        foreach ($clonedDataConference->meta as $meta) {
            $clonedMeta = $meta->replicate();
            $clonedMeta->metable_id = $clonedDataConference->id;
            $clonedMeta->save();
        }

        $clonedDataConference->load('meta');
    }

    private function generateUniquePath($basePath)
    {
        $uniquePath = $basePath;
        $counter = 1;

        while (Conference::where('path', $uniquePath)->exists()) {
            $counter++;
            $uniquePath = $basePath.$counter;
        }

        return $uniquePath;
    }
}
