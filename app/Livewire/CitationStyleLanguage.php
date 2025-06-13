<?php

namespace App\Livewire;

use App\Facades\Citation;
use App\Models\Submission;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CitationStyleLanguage extends Component
{
    #[Locked]
    public Submission $submission;

    #[Locked]
    public string $citationStyle = 'apa';

    public function mount()
    {
        $this->citationStyle = app()->getCurrentScheduledConference()->getMeta('primary_citation_format');
    }

    public function render()
    {
        return view('livewire.citation-style-language', [
            'citation' => Citation::getCitation($this->submission, $this->citationStyle),
            'citationStyles' => Citation::getEnabledCitationStyles(),
            'citationDownloads' => Citation::getEnabledCitationDownloads(),
        ]);
    }

    public function updateCitationStyle($style): void
    {
        $this->citationStyle = $style;
    }

    public function downloadCitation($citationStyle)
    {
        $styleConfig = Citation::getCitationStyleConfig($citationStyle);

        if (empty($styleConfig)) {
            return false;
        }

        $citation = trim(strip_tags(Citation::getCitation($this->submission, $citationStyle)));
        $encodedFilename = $this->submission->getMeta('title').'.'.$styleConfig['fileExtension'];
        $headers = [
            'Content-Type' => $styleConfig['contentType'],
        ];

        return response()->streamDownload(
            function () use ($citation) {
                echo $citation;
            },
            $encodedFilename,
            $headers,
        );
    }
}
