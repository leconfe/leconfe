<?php

namespace App\Frontend\Conference\Pages;

use App\Frontend\ScheduledConference\Pages as ScheduledConferencePages;
use App\Frontend\Website\Pages\Page;
use App\Http\Middleware\RedirectToScheduledConference;
use App\Models\Conference;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Oai extends Page
{
    protected static string|array $withoutRouteMiddleware = [
        RedirectToScheduledConference::class,
    ];

    /**
     * Handle the incoming request for the OAI-PMH endpoint.
     *
     * This method extracts the conference path from the request URL,
     * retrieves the corresponding `Conference` model, and delegates
     * the request handling to the `handleRequest` method.
     *
     * @return Response|null
    */
    public function __invoke()
    {
        $request = request();

        $pathInfos = explode('/', $request->getPathInfo());
        $conference = Conference::where('path', $pathInfos[1])->first();
        return $this->handleRequest($conference, $request);
    }

    /**
     * Handle the OAI-PMH request.
     *
     * @param Conference $conference
     * @param Request $request
     * @return Response|null
     */
    public function handleRequest(Conference $conference, Request $request): ?Response
    {
        $verb = $request->input('verb');

        switch ($verb) {
            case 'Identify':
                return $this->identify($conference);
            case 'ListRecords':
                return $this->listRecords($request);
            case 'ListIdentifiers':
                return $this->listIdentifiers($request);
            case 'GetRecord':
                return $this->getRecord($request);
            case 'ListSets':
                return $this->listSets($request);
            case 'ListMetadataFormats':
                return $this->listMetadataFormats($request);
            default:
                return $this->errorResponse('badVerb', 'Illegal OAI verb');
        }
    }

    public function getRecord(Request $request): Response
    {
        return response()->view('frontend.conference.pages.oai.get-record', [
            'identifier' => $request->input('identifier'),
            'metadataPrefix' => $request->input('metadataPrefix'),
            'baseURL' => route('livewirePageGroup.conference.pages.oai'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    public function listSets(Request $request): Response
    {
//        Conference::where('')

        return response()->view('frontend.conference.pages.oai.list-sets', [
            'baseURL' => route('livewirePageGroup.conference.pages.oai'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    public function identify(Conference $conference): Response
    {
//        dd($conference->submission()->get());
        return response()->view('frontend.conference.pages.oai.identify', [
            'repositoryName' => $conference->name,
            'baseURL' => route('livewirePageGroup.conference.pages.oai'),
            'protocolVersion' => '2.0',
            'adminEmail' => config('mail.from.address'),
            'earliestDatestamp' => '2020-01-01', // Adjust based on your earliest paper
            'deletedRecord' => 'transient',
            'granularity' => 'YYYY-MM-DD',
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    public function listRecords(Request $request)
    {

    }

    public function listMetadataFormats(Request $request): Response
    {
        $metadataFormat = [
            [
                'metadataPrefix' => 'oai_dc',
                'schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
                'metadataNamespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
            ],
            [
                'metadataPrefix' => 'marcxml',
                'schema' => 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd',
                'metadataNamespace' => 'http://www.loc.gov/MARC21/slim',
            ],
            [
                'metadataPrefix' => 'rfc1807',
                'schema' => 'http://www.openarchives.org/OAI/1.1/rfc1807.xsd',
                'metadataNamespace' => 'http://info.internet.isi.edu:80/in-notes/rfc/files/rfc1807.txt',
            ],
            [
                'metadataPrefix' => 'oai_marc',
                'schema' => 'http://www.openarchives.org/OAI/1.1/oai_marc.xsd',
                'metadataNamespace' => 'http://www.openarchives.org/OAI/1.1/oai_marc',
            ]
        ];

        return response()->view('frontend.conference.pages.oai.list-metadata-formats', [
            'metadataFormatArr' => collect($metadataFormat),
            'baseURL' => route('livewirePageGroup.conference.pages.oai'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    public function listIdentifiers(Request $request)
    {

    }

    protected function errorResponse($code, $message): Response
    {
        return response()->view('frontend.conference.pages.oai.error', [
            'code' => $code,
            'message' => $message,
            'baseURL' => route('livewirePageGroup.conference.pages.oai'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    private function xslPath(): string
    {
        return asset('css/oai2.xsl');
    }
}
