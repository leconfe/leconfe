<?php

namespace Tests\Feature;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpClientHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_leconfe_version_headers_are_safe_when_code_version_has_line_break(): void
    {
        File::partialMock()
            ->shouldReceive('get')
            ->with(base_path('version'))
            ->andReturn("1.4.5\n");

        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], '{}'),
        ]));
        $handler->push(Middleware::history($history));

        Http::withOptions(['handler' => $handler])->get('https://leconfe.test/plugins');

        $this->assertCount(1, $history);
        $request = $history[0]['request'];

        $this->assertSame('1.4.5', $request->getHeaderLine('Leconfe-Version'));
        $this->assertSame('Leconfe/1.4.5', $request->getHeaderLine('User-Agent'));
    }
}
