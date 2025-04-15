<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use Illuminate\Http\Request;
use OCC\OAI2\Response;

class OAIController extends Controller
{
    /**
     * Handle the OAI-PMH request.
     *
     * @param Conference $conference
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handleRequest(Conference $conference, Request $request)
    {
        dd($conference);
        $verb = $request->input('verb');

        switch ($verb) {
            case 'Identify':
                return $this->identify();
            case 'ListRecords':
                return $this->listRecords($request);
            case 'ListIdentifiers':
                return $this->listIdentifiers($request);
            case 'GetRecord':
                return $this->getRecord($request);
            case 'ListSets':
                return $this->listSets($request);
            case 'ListMetadataFormats':
                return $this->listMetadaFormats($request);
            default:
                return $this->errorResponse('badVerb', 'Illegal OAI verb');
        }
    }

    public function getRecord(Request $request)
    {
        return response()->view('panel.oai.get-record', [
            'identifier' => $request->input('identifier'),
            'metadataPrefix' => $request->input('metadataPrefix'),
            'baseURL' => route('oai-pmh'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    public function listSets(Request $request)
    {
//        Conference::where('')

        return response()->view('panel.oai.list-sets', [
            'baseURL' => route('oai-pmh'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    public function identify()
    {
        return response()->view('panel.oai.identify', [
            'repositoryName' => 'Leconfe Conference Papers',
            'baseURL' => route('oai-pmh'),
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

    public function listMetadaFormats(Request $request)
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

        return response()->view('panel.oai.list-metadata-formats', [
            'metadataFormatArr' => collect($metadataFormat),
            'baseURL' => route('oai-pmh'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    public function listIdentifiers(Request $request)
    {

    }

    protected function errorResponse($code, $message)
    {
        return response()->view('panel.oai.error', [
            'code' => $code,
            'message' => $message,
            'baseURL' => route('oai-pmh'),
            'xsl' => $this->xslPath(),
        ])->header('Content-Type', 'text/xml');
    }

    private function xslPath()
    {
        return asset('css/oai2.xsl');
    }
}
