<?php

namespace App\Managers;

use App\Facades\Setting;
use App\Models\Submission;
use Illuminate\Support\Facades\App;
use Seboettg\CiteProc\CiteProc;

class CitationManager
{
    public function getCitationStyles(): array
    {
        $defaults = [
            [
                'id' => 'acm-sig-proceedings',
                'title' => 'ACM',
                'isEnabled' => true,
            ],
            [
                'id' => 'acs-nano',
                'title' => 'ACS',
                'isEnabled' => true,
            ],
            [
                'id' => 'apa',
                'title' => 'APA',
                'isEnabled' => true,
            ],
            [
                'id' => 'associacao-brasileira-de-normas-tecnicas',
                'title' => 'ABNT',
                'isEnabled' => true,
            ],
            [
                'id' => 'chicago-author-date',
                'title' => 'Chicago',
                'isEnabled' => true,
            ],
            [
                'id' => 'harvard-cite-them-right',
                'title' => 'Harvard',
                'isEnabled' => true,
            ],
            [
                'id' => 'ieee',
                'title' => 'IEEE',
                'isEnabled' => true,
            ],
            [
                'id' => 'modern-language-association',
                'title' => 'MLA',
                'isEnabled' => true,
            ],
            [
                'id' => 'turabian-fullnote-bibliography',
                'title' => 'Turabian',
                'isEnabled' => true,
            ],
            [
                'id' => 'vancouver',
                'title' => 'Vancouver',
                'isEnabled' => true,
            ],
            [
                'id' => 'ama',
                'title' => 'AMA',
                'isEnabled' => true,
            ],
        ];

        return $defaults;
    }

    public function getEnabledCitationStyles(): array
    {
        $enabledStyles = app()->getCurrentConference()->getMeta('enabled_citation_styles');
        $styles = $this->getCitationStyles();

        return array_filter($styles, function ($style) use ($enabledStyles) {
            return in_array($style['id'], $enabledStyles);
        });
    }

    public function getCitationDownloads(): array
    {
        $defaults = [
            [
                'id' => 'ris',
                'title' => 'Endnote / Zotero / Mendeley (RIS – Research Information Systems)',
                'isEnabled' => true,
                'useView' => 'livewire.citation-styles.ris',
                'fileExtension' => 'ris',
                'contentType' => 'application/x-Research-Info-Systems',
            ],
            [
                'id' => 'bibtex',
                'title' => 'BibTeX',
                'isEnabled' => true,
                'fileExtension' => 'bib',
                'contentType' => 'application/x-bibtex',
            ],
        ];

        return $defaults;
    }

    public function getEnabledCitationDownloads(): array
    {
        $enabledStyles = app()->getCurrentConference()->getMeta('downloadable_citation_formats');
        $styles = $this->getCitationDownloads();

        return array_filter($styles, function ($style) use ($enabledStyles) {
            return in_array($style['id'], $enabledStyles);
        });
    }

    /**
     * Get citation config for a citation ID (example: 'apa')
     */
    public function getCitationStyleConfig(string $styleId): array
    {
        $styleConfigs = array_merge($this->getCitationStyles(), $this->getCitationDownloads());
        $styleConfig = array_filter($styleConfigs, function ($styleConfig) use ($styleId) {
            return $styleConfig['id'] === $styleId;
        });

        return array_shift($styleConfig);
    }

    public function loadStyle(array $styleConfig): false|string
    {
        $path = empty($styleConfig['useCsl'])
            ? base_path('data/citation-styles/').$styleConfig['id'].'.csl'
            : $styleConfig['useCsl'];

        return file_get_contents($path);
    }

    public function getCitation(Submission $paper, $citationStyle = 'apa'): string
    {
        $citationData = new \stdClass;
        $citationData->type = 'paper-conference';
        $citationData->id = $paper->getKey();
        $citationData->title = $paper->getMeta('title');
        $citationData->{'container-title'} = $paper->conference->name;
        $citationData->volume = $paper->proceeding->volume;
        $citationData->issue = $paper->proceeding->number;
        if ($paper->getMeta('article_pages')) {
            $citationData->page = $paper->getMeta('article_pages');
        }

        $citationData->section = $paper->track->title;
        $citationData->keywords = $paper->getMeta('keywords') ?? [];
        $citationData->abstract = strip_tags($paper->getMeta('abstract'));
        foreach ($paper->authors as $author) {
            $currentAuthor = new \stdClass;

            if (! empty($currentAuthor->family)) {
                $currentAuthor->family = $author->family_name ?: null;
                $currentAuthor->given = $author->given_name;
            } else {
                $currentAuthor->family = $author->given_name;
            }

            $authorsGroups = app()->getCurrentConference()->getMeta('citation_contributor_authors') ?? [];
            $translatorsGroups = app()->getCurrentConference()->getMeta('citation_contributor_translators') ?? [];

            switch (true) {
                case in_array($author->author_role_id, $translatorsGroups):
                    if (! isset($citationData->translator)) {
                        $citationData->translator = [];
                    }
                    $citationData->translator[] = $currentAuthor;
                    break;
                case in_array($author->author_role_id, $authorsGroups):
                    if (! isset($citationData->author)) {
                        $citationData->author = [];
                    }
                    $citationData->author[] = $currentAuthor;
                    break;
                default:
                    if (! isset($citationData->author)) {
                        $citationData->author = [];
                    }
                    break;
            }
        }

        $citationData->URL = $paper->getUrl();
        if ($paper->doi?->doi) {
            $citationData->DOI = $paper->doi->doi;
        }
        $citationData->languages = [Setting::get('default_language', 'en')];

        $citationData->{'container-title-short'} = $paper->conference->path;

        $accessed = new \stdClass;
        $accessed->raw = date('Y-m-d');
        $citationData->accessed = $accessed;

        $issued = new \stdClass;
        $issued->raw = $paper->published_at?->format('Y-m-d');
        $citationData->issued = $issued;

        $styleConfig = $this->getCitationStyleConfig($citationStyle);

        if (empty($styleConfig)) {
            return '';
        }

        if (! empty($styleConfig['useView'])) {
            return view($styleConfig['useView'], compact('citationData'))->render();
        }

        $style = $this->loadStyle($styleConfig);

        if (! $style) {
            return '';
        }

        // Determine what locale to use. Try in order:
        //  - xx_YY
        //  - xx
        // Fall back English if none found.
        foreach (
            [
                str_replace('_', '-', substr(App::getLocale(), 0, 5)),
                substr(App::getLocale(), 0, 2),
                'en-US',
            ] as $tryLocale
        ) {
            if (file_exists(base_path('/vendor/citation-style-language/locales/locales-'.$tryLocale.'.xml'))) {
                break;
            }
        }

        //Clickable URL and DOI including affixes
        $additionalMarkup = [
            'DOI' => [
                'function' => function ($item, $renderedValue) {
                    return <<<HTML
                        <a href="https://doi.org/{$item->DOI}">{$renderedValue}</a>
                    HTML;
                },
                'affixes' => true,
            ],
            'URL' => [
                'function' => function ($item, $renderedValue) {
                    return <<<HTML
                        <a href="https://doi.org/{$item->URL}">{$renderedValue}</a>
                    HTML;
                },
                'affixes' => true,
            ],
        ];

        $citeProc = new CiteProc($style, $tryLocale, $additionalMarkup);

        return $citeProc->render([$citationData], 'bibliography');
    }
}
