<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApplicationVersionTest extends TestCase
{
    public function test_code_version_trims_version_file_contents(): void
    {
        File::shouldReceive('get')
            ->once()
            ->with(base_path('version'))
            ->andReturn("1.5.0-beta.1\n");

        $this->assertSame('1.5.0-beta.1', app()->getCodeVersion());
    }
}
