<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Permissions\PermissionPopulateAction;
use App\Models\Timeline;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class FrenchSubmissionInstructionsTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    public function test_new_submission_instructions_render_as_french_paragraphs(): void
    {
        $this->enableLanguages(['fr'], 'fr');
        $conference = $this->makeConference();
        $this->useConference($conference, $this->makeScheduledConference($conference));
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Gate::before(fn () => true);
        PermissionPopulateAction::run();
        Timeline::query()->create([
            'name' => 'Submission open',
            'date' => now()->subDay(),
            'type' => Timeline::TYPE_SUBMISSION_OPEN,
        ]);
        $this->makeTrack();
        $this->actingAs($this->makeAuthor());
        App::setLocale('fr');

        Livewire::test(CreateSubmission::class)
            ->assertSeeHtml('<p>Veuillez lire nos directives de soumission')
            ->assertSeeHtml('<li>Le fichier de soumission est au format OpenOffice, Microsoft Word ou RTF.</li>')
            ->assertDontSeeHtml('</p>\n')
            ->assertDontSeeHtml('</li>\n');
    }

    public function test_no_translation_contains_a_literal_backslash_n(): void
    {
        $offenders = [];

        foreach (glob(lang_path('*/*.php')) as $file) {
            $strings = new RecursiveIteratorIterator(new RecursiveArrayIterator(require $file));

            foreach ($strings as $key => $value) {
                if (is_string($value) && str_contains($value, '\n')) {
                    $offenders[] = basename(dirname($file)).'/'.basename($file).": {$key}";
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
