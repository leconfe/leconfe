<?php

namespace Tests\Feature\Multilingual;

use App\Classes\ImportExport\ExportArticleCrossref;
use App\Facades\Citation;
use App\Models\AuthorRole;
use App\Models\Conference;
use App\Models\DOI;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class SubmissionExternalOutputsTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected const CROSSREF_NAMESPACE = 'http://www.crossref.org/schema/5.3.0';

    protected const JATS_NAMESPACE = 'http://www.ncbi.nlm.nih.gov/JATS1';

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $author;

    protected Submission $paper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);
        $this->conference->setMeta('primary_citation_format', 'apa');

        $this->enableLanguages(['en', 'id', 'fr']);

        $this->author = $this->makeAuthor();

        $this->paper = $this->makePaper($this->author, 'id', [
            'title' => ['id' => 'Terumbu & karang', 'en' => 'Coral reefs'],
            'subtitle' => ['id' => 'Catatan lapangan', 'en' => 'Field notes'],
            'abstract' => ['id' => "<p>Kajian <strong>terumbu</strong>\n karang</p>", 'en' => '<p>A study of coral reefs</p>'],
            'keywords' => ['id' => ['laut', 'karang'], 'en' => ['sea']],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $this->makeProceeding());

        $this->paper->authors()->create([
            'author_role_id' => AuthorRole::query()->firstOrCreate(['name' => 'Author'])->getKey(),
            'given_name' => 'Ayu',
            'family_name' => 'Lestari',
            'email' => 'ayu@example.test',
        ]);
    }

    public function test_paper_page_meta_tags_use_the_paper_language_and_list_translations(): void
    {
        $this->visitPaper($this->conference, $this->paper, 'en')
            ->assertSee('<meta name="citation_title" content="Terumbu &amp; karang">', false)
            ->assertSee('<meta name="citation_abstract" content="Kajian terumbu karang">', false)
            ->assertSee('<meta name="citation_keywords" content="laut">', false)
            ->assertSee('<meta name="citation_keywords" content="karang">', false)
            ->assertDontSee('<meta name="citation_keywords" content="sea">', false)
            ->assertSee('<meta name="citation_language" content="id">', false)
            ->assertSee('<meta name="DC.Title" content="Terumbu &amp; karang" xml:lang="id">', false)
            ->assertSee('<meta name="DC.Title.Alternative" content="Coral reefs" xml:lang="en">', false)
            ->assertSee('<meta name="DC.Description" content="Kajian terumbu karang" xml:lang="id">', false)
            ->assertSee('<meta name="DC.Description" content="A study of coral reefs" xml:lang="en">', false)
            ->assertSee('<meta name="DC.Language" content="id">', false)
            ->assertSee('<meta property="og:title" content="Coral reefs">', false);

        $this->visitPaper($this->conference, $this->paper, 'fr')
            ->assertSee('<meta name="citation_title" content="Terumbu &amp; karang">', false)
            ->assertSee('<meta property="og:title" content="Terumbu &amp; karang">', false)
            ->assertDontSee('&amp;amp;', false)
            ->assertDontSee('DC.Title.Alternative" content="Terumbu', false);
    }

    public function test_citation_data_uses_the_paper_language(): void
    {
        app()->setLocale('en');

        $data = Citation::getCitationData($this->paper);

        $this->assertSame('Terumbu & karang', $data->title);
        $this->assertSame('id', $data->language);
        $this->assertSame(['laut', 'karang'], $data->keywords);
        $this->assertStringStartsWith('Kajian terumbu', $data->abstract);
        $this->assertObjectNotHasProperty('languages', $data);

        $this->assertStringContainsString('Terumbu &amp; karang', Citation::getCitation($this->paper, 'apa'));
        $this->assertStringContainsString('LA  - id', Citation::getCitation($this->paper, 'ris'));

        $this->paper->setMeta('language', 'pt_BR');

        $this->assertSame('pt-BR', Citation::getCitationData(Submission::query()->find($this->paper->getKey()))->language);
    }

    public function test_crossref_export_uses_the_paper_language_and_adds_translated_abstracts(): void
    {
        $xpath = $this->crossrefXpath($this->paper);

        $paper = $xpath->query('//cr:conference_paper')->item(0);

        $this->assertNotNull($paper);
        $this->assertSame('id', $paper->getAttribute('language'));
        $this->assertSame('Terumbu & karang', $xpath->evaluate('string(cr:titles/cr:title)', $paper));
        $this->assertSame('Catatan lapangan', $xpath->evaluate('string(cr:titles/cr:subtitle)', $paper));

        $abstracts = $xpath->query('jats:abstract', $paper);

        $this->assertSame(2, $abstracts->length);
        $this->assertSame('id', $abstracts->item(0)->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        $this->assertStringStartsWith('Kajian terumbu', $xpath->evaluate('string(jats:p)', $abstracts->item(0)));
        $this->assertSame('en', $abstracts->item(1)->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        $this->assertSame('A study of coral reefs', $xpath->evaluate('string(jats:p)', $abstracts->item(1)));

        // The order the conference_paper sequence of the schema requires.
        $children = collect(iterator_to_array($paper->childNodes))
            ->filter(fn ($node) => $node->nodeType === XML_ELEMENT_NODE)
            ->map(fn ($node) => $node->nodeName)
            ->values()
            ->all();

        $this->assertSame(['contributors', 'titles', 'jats:abstract', 'jats:abstract', 'publication_date', 'doi_data'], $children);
    }

    public function test_crossref_export_leaves_out_codes_the_schema_does_not_list(): void
    {
        $this->paper->setMeta('language', 'ckb');

        $xpath = $this->crossrefXpath(Submission::query()->find($this->paper->getKey()));
        $paper = $xpath->query('//cr:conference_paper')->item(0);

        $this->assertFalse($paper->hasAttribute('language'));
        $this->assertSame('ckb', $xpath->query('jats:abstract', $paper)->item(0)->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
    }

    protected function crossrefXpath(Submission $submission): DOMXPath
    {
        app()->getSite()->setMeta('publisher_name', 'Leconfe Press');
        $this->conference->setManyMeta([
            'doi_crossref_depositor_name' => 'Depositor',
            'doi_crossref_depositor_email' => 'depositor@example.test',
        ]);

        if (! $submission->doi) {
            DOI::query()->forceCreate([
                'conference_id' => $this->conference->getKey(),
                'doiable_type' => $submission->getMorphClass(),
                'doiable_id' => $submission->getKey(),
                'doi' => '10.1234/leconfe.'.$submission->getKey(),
            ]);
        }

        $submission = Submission::query()->with(['doi', 'authors', 'translate'])->find($submission->getKey());

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML((new ExportArticleCrossref($submission))->exportXml()));

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cr', self::CROSSREF_NAMESPACE);
        $xpath->registerNamespace('jats', self::JATS_NAMESPACE);

        return $xpath;
    }
}
