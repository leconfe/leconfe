<?php

namespace App\Classes\ImportExport;

use App\Models\Enums\DOIStatus;
use App\Models\Submission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\ArrayToXml\ArrayToXml;

class ExportArticleCrossref
{
    protected string $testUrl = 'https://test.crossref.org/v2/';

    protected string $productionUrl = 'https://api.crossref.org/v2/';

    public string $batchId;

    public ArrayToXml $arrayData;

    public function __construct(
        public Submission $submission,
    ) {
        $this->batchId = $this->generateBatchId();
        $this->arrayData = $this->createArrayData();
    }

    public function generateBatchId()
    {
        return 'leconfe-' . $this->submission->getKey() . '-' . Str::random(5);
    }

    public function getDepositUrl(): string
    {
        return $this->getUrl() . 'deposits';
    }

    public function getUrl(): string
    {
        return $this->submission->conference->getMeta('doi_crossref_test') ? $this->testUrl : $this->productionUrl;
    }

    public function exportXml()
    {
        return $this->arrayData->toXml();
    }

    public function depositXml()
    {
        $conference = $this->submission->conference;

        $url = $this->getDepositUrl();
        $username = $conference->getMeta('doi_crossref_username');
        $password = $conference->getMeta('doi_crossref_password');

        $response = Http::attach('mdFile', $this->exportXml(), $this->batchId . '.xml')
            ->post($url, [
                'operation' => 'doMDUpload',
                'usr' => $username,
                'pwd' => $password,
            ]);

        $responseContent = $response->getBody()->getContents();

        if ($response->unauthorized()) {
            throw new \Exception('Unauthorized to deposit to crossref. Please check your username and password.');
        } elseif ($response->forbidden()) {
            $xmlDoc = new \DOMDocument('1.0', 'utf-8');
            $xmlDoc->loadXML($responseContent);

            $batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);
            $msg = $xmlDoc->getElementsByTagName('msg')->item(0)->nodeValue;
            $this->updateDepositStatus(DOIStatus::Error, $batchIdNode->nodeValue, $msg, $responseContent);

            throw new \Exception($msg);
        } elseif ($response->clientError() || $response->serverError()) {
            $message = $responseContent . ' (' . $response->status() . ')';

            $this->updateDepositStatus(DOIStatus::Error, $this->batchId, $message, $message);

            throw new \Exception($message);
        }

        $xmlDoc = new \DOMDocument('1.0', 'utf-8');
        $xmlDoc->loadXML($responseContent);
        $batchIdNode = $xmlDoc->getElementsByTagName('batch_id')->item(0);
        $submissionIdNode = $xmlDoc->getElementsByTagName('submission_id')->item(0);
        $successMessage = 'The metadata for this item has been deposited with Crossref. To view further details, see the submission in the <a href="https://doi.crossref.org/servlet/submissionAdmin?sf=detail&submissionID=' . $submissionIdNode->nodeValue . '">Crossref admin panel</a>.';

        // Get the DOI deposit status
        // If the deposit failed
        $failureCountNode = $xmlDoc->getElementsByTagName('failure_count')->item(0);
        $failureCount = (int) $failureCountNode->nodeValue;
        $result = false;
        $status = null;
        if ($failureCount > 0) {
            $status = DOIStatus::Error;
            $result = false;
        } else {
            // Deposit was received
            $status = DOIStatus::Registered;
            $result = true;
        }

        // Update the status
        if ($status) {
            $this->updateDepositStatus($status, $batchIdNode->nodeValue, $successMessage, $responseContent);
        }

        return $result;
    }

    public function updateDepositStatus(DOIStatus $status, string $batchId, ?string $message = null, ?string $fullMessage = null)
    {
        $doi = $this->submission->doi;
        $doi->status = $status;
        $doi->save();

        $doi->setManyMeta([
            'crossref_message' => $message,
            'crossref_full_message' => $fullMessage,
            'crossref_batch_id' => $batchId,
        ]);

        if ($status == DOIStatus::Registered) {
            $doi->setMeta('registration_agency', 'crossref');
        }
    }

    public function createArrayData(): ArrayToXml
    {
        $metadata = [];

        $metadata['head'] = $this->createHead();
        $metadata['body'] = $this->createBody();

        $arrayToXml = new ArrayToXml(
            $metadata,
            [
                'rootElementName' => 'doi_batch',
                '_attributes' => [
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xsi:schemaLocation' => 'http://www.crossref.org/schema/5.3.0 https://www.crossref.org/schemas/crossref5.3.0.xsd',
                    'xmlns' => 'http://www.crossref.org/schema/5.3.0',
                    'xmlns:jats' => 'http://www.ncbi.nlm.nih.gov/JATS1',
                    'xmlns:fr' => 'http://www.crossref.org/fundref.xsd',
                    'xmlns:mml' => 'http://www.w3.org/1998/Math/MathML',
                    'version' => '5.3.0',
                ],
            ],
            true,
            'UTF-8',
            '1.0',
            [],
        );

        return $arrayToXml->prettify();
    }

    protected function createHead()
    {
        $site = app()->getSite();

        $conference = $this->submission->conference;
        $scheduledConference = $this->submission->scheduledConference;

        return [
            'doi_batch_id' => $this->batchId,
            'timestamp' => date('YmdHis') . '00',
            'depositor' => [
                'depositor_name' => $conference->getMeta('doi_crossref_depositor_name'),
                'email_address' => $conference->getMeta('doi_crossref_depositor_email'),
            ],
            'registrant' => $site->getMeta('publisher_name'),
        ];
    }

    protected function createBody()
    {
        $body = [
            'conference' => [
                'event_metadata' => $this->createEventMetadata(),
                'proceedings_metadata' => $this->createProceedingsMetadata(),
                'conference_paper' => $this->createConferencePaper(),
            ],
        ];

        return $body;
    }

    protected function createEventMetadata(): array
    {
        $scheduledConference = $this->submission->scheduledConference;

        $metadata = [
            'conference_name' => $scheduledConference->title,
        ];

        if ($scheduledConference->getMeta('theme_information')) {
            $metadata['conference_theme'] = $scheduledConference->getMeta('theme_information');
        }

        if ($scheduledConference->getMeta('acronym')) {
            $metadata['conference_acronym'] = $scheduledConference->getMeta('acronym');
        }

        if ($scheduledConference->getMeta('number')) {
            $metadata['conference_number'] = $scheduledConference->getMeta('number');
        }

        if ($scheduledConference->getMeta('location')) {
            $metadata['conference_location'] = $scheduledConference->getMeta('location');
        }

        if ($scheduledConference->date_start && $scheduledConference->date_end) {
            $metadata['conference_date'] = [
                '_attributes' => [
                    'start_day' => $scheduledConference->date_start->format('d'),
                    'start_month' => $scheduledConference->date_start->format('m'),
                    'start_year' => $scheduledConference->date_start->format('Y'),
                    'end_day' => $scheduledConference->date_end->format('d'),
                    'end_month' => $scheduledConference->date_end->format('m'),
                    'end_year' => $scheduledConference->date_end->format('Y'),
                ],
                '_value' => $scheduledConference->date_start->format('M. d') . ' - ' . $scheduledConference->date_end->format('M. d, Y'),
            ];
        }

        return $metadata;
    }

    protected function createProceedingsMetadata(): array
    {
        $site = app()->getSite();
        $proceeding = $this->submission->proceeding;


        if (!$proceeding) {
            throw new \Exception('Submission does not have a proceeding');
        }

        $metadata = [
            'proceedings_title' => $proceeding->title,
        ];

        if (!$site->getMeta('publisher_name')) {
            throw new \Exception('Publisher name cannot be empty, please set it in the Administration > Website Setting > Publishing Details');
        }

        $metadata['publisher']['publisher_name'] = $site->getMeta('publisher_name');

        if ($site->getMeta('publisher_location')) {
            $metadata['publisher']['publisher_place'] = $site->getMeta('publisher_location');
        }

        if ($proceeding->published_at) {
            $metadata['publication_date'] = [
                '_attributes' => [
                    'media_type' => 'online',
                ],
                'month' => $proceeding->published_at->format('m'),
                'day' => $proceeding->published_at->format('d'),
                'year' => $proceeding->published_at->format('Y'),
            ];
        }

        $metadata['noisbn'] = [
            '_attributes' => [
                'reason' => 'simple_series',
            ],
        ];

        return $metadata;
    }

    protected function createSeriesMetadata(): array
    {
        $conference = $this->submission->conference;

        $metadata = [
            'titles' => [
                'title' => $conference->name,
            ],
        ];

        if (! $conference->getMeta('issn')) {
            throw new \Exception('ISSN cannot be empty');
        }

        $metadata['issn'] = $conference->getMeta('issn');

        return $metadata;
    }

    protected function createConferencePaper(): array
    {
        $authors = $this->submission->authors;
        $proceeding = $this->submission->proceeding;

        $metadata = [];

        if ($authors->isNotEmpty()) {
            $metadata['contributors'] = [
                'person_name' => $authors->map(function ($author, $index) {
                    return [
                        '_attributes' => [
                            'sequence' => $index === 0 ? 'first' : 'additional',
                            'contributor_role' => 'author',
                        ],
                        'given_name' => $author->given_name,
                        'surname' => $author->family_name,
                    ];
                })->toArray(),
            ];
        }

        $metadata['titles'] = [
            'title' => $this->submission->getMeta('title'),
        ];

        $metadata['jats:abstract'] = [
            'jats:p' => strip_tags($this->submission->getMeta('abstract')),
        ];

        $metadata['publication_date'] = [
            '_attributes' => [
                'media_type' => 'online',
            ],
            'month' => $this->submission->published_at->format('m'),
            'day' => $this->submission->published_at->format('d'),
            'year' => $this->submission->published_at->format('Y'),
        ];

        $metadata['doi_data'] = [
            'doi' => $this->submission->doi->doi,
            'resource' => route('livewirePageGroup.conference.pages.paper', ['submission' => $this->submission]),
        ];

        return $metadata;
    }
}
