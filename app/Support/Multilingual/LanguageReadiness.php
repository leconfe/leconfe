<?php

namespace App\Support\Multilingual;

use App\Mail\Templates\PublishSubmissionMail;
use App\Models\MailTemplate;
use App\Models\Proceeding;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * What readers and email recipients get in each enabled language once a paper or a
 * proceeding is published. It informs the editor and never blocks publication.
 */
class LanguageReadiness
{
    /**
     * @param  array<string, array<int, string>>  $missing  Labels of the fields that fall back to the primary language, keyed by locale.
     * @param  array<string, int>  $incompletePapers  Number of papers with missing metadata, keyed by locale.
     */
    public function __construct(
        public readonly string $primaryLocale,
        public readonly array $missing,
        public readonly bool $isPaper = false,
        public readonly ?string $emailLocale = null,
        public readonly ?string $emailContentLocale = null,
        public readonly int $paperCount = 0,
        public readonly array $incompletePapers = [],
    ) {}

    public static function forPaper(Submission $submission): self
    {
        $emailLocale = RecipientLocale::for($submission->user);

        return new self(
            primaryLocale: $submission->getPrimaryContentLocale(),
            missing: static::missingFields($submission, static::paperFields()),
            isPaper: true,
            emailLocale: $emailLocale,
            emailContentLocale: static::mailContentLocale(PublishSubmissionMail::class, $emailLocale),
        );
    }

    public static function forProceeding(Proceeding $proceeding): self
    {
        $papers = $proceeding->submissions()->get();
        $incompletePapers = [];

        foreach ($papers as $paper) {
            foreach (static::missingFields($paper, static::paperFields()) as $locale => $fields) {
                $incompletePapers[$locale] = ($incompletePapers[$locale] ?? 0) + ($fields ? 1 : 0);
            }
        }

        return new self(
            primaryLocale: $proceeding->getPrimaryContentLocale(),
            missing: static::missingFields($proceeding, [
                'title' => __('general.title'),
                'description' => __('general.description'),
            ]),
            paperCount: $papers->count(),
            incompletePapers: $incompletePapers,
        );
    }

    /**
     * For each enabled language other than the record's primary language, the labels of
     * the fields that have primary-language content but no translation.
     *
     * @param  array<string, string>  $fields  Field labels keyed by translatable key.
     * @return array<string, array<int, string>>
     */
    public static function missingFields(Model $record, array $fields): array
    {
        $primary = $record->getPrimaryContentLocale();

        return collect(app(ContentLocales::class)->ordered())
            ->reject(fn (string $locale) => $locale === $primary)
            ->mapWithKeys(fn (string $locale) => [$locale => collect($fields)
                ->filter(fn (string $label, string $key) => ! $record::isBlankContent($record->translated($key, $primary))
                    && $record::isBlankContent($record->getStoredTranslation($key, $locale)))
                ->values()
                ->all(),
            ])
            ->all();
    }

    /**
     * The language of the template text an email in $locale is written from. Without a
     * customised template the built-in English text is used.
     */
    protected static function mailContentLocale(string $mailable, string $locale): string
    {
        $template = MailTemplate::query()
            ->forCurrentConference()
            ->where('mailable', $mailable)
            ->first();

        if (! $template) {
            return 'en';
        }

        return $template->translatedLocale('html_template', $locale) ?? $template->getPrimaryContentLocale();
    }

    /**
     * @return array<string, string>
     */
    protected static function paperFields(): array
    {
        return [
            'title' => __('general.title'),
            'subtitle' => __('general.subtitle'),
            'abstract' => __('general.abstract'),
            'keywords' => __('general.keywords'),
        ];
    }

    public function render(): View
    {
        return view('components.language-readiness', [
            'readiness' => $this,
            'locales' => app(ContentLocales::class),
        ]);
    }
}
