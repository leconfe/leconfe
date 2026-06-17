<?php

namespace Tests\Unit;

use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\RevisionFiles;
use Tests\TestCase;

class RevisionFilesTableTest extends TestCase
{
    public function test_revisions_table_has_no_upload_instruction_description(): void
    {
        $this->assertSame('', (new RevisionFiles())->tableDescription());
    }
}
