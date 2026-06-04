<?php

namespace Tests\Feature;

use App\Frontend\Website\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_login_uses_simple_panel_layout(): void
    {
        $this->withoutVite()
            ->get(route(Login::getRouteName('website')))
            ->assertOk()
            ->assertSee('fi-simple-layout', false)
            ->assertSee('fi-simple-page', false)
            ->assertSee('class="fi-simple-link"', false)
            ->assertSee('fi-input', false)
            ->assertSee('Show password', false)
            ->assertSee('Hide password', false)
            ->assertDontSee('website::layouts.main', false)
            ->assertDontSee('link link-primary', false)
            ->assertDontSee('input input-sm', false)
            ->assertDontSee('btn btn-primary', false);
    }
}
