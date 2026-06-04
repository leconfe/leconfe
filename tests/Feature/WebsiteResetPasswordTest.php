<?php

namespace Tests\Feature;

use App\Frontend\Website\Pages\ResetPassword;
use App\Mail\Templates\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class WebsiteResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_reset_password_uses_simple_panel_layout(): void
    {
        $this->withoutVite()
            ->get(route(ResetPassword::getRouteName('website')))
            ->assertOk()
            ->assertSee('fi-simple-layout', false)
            ->assertSee('fi-simple-page', false)
            ->assertSee('fi-input', false)
            ->assertDontSee('website::layouts.main', false)
            ->assertDontSee('input input-sm', false)
            ->assertDontSee('btn btn-primary', false);
    }

    public function test_reset_password_submit_still_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset-password@example.test',
            'password' => Hash::make('password12345'),
        ]);

        Livewire::test(ResetPassword::class)
            ->set('email', $user->email)
            ->call('submit')
            ->assertSet('success', true);

        Mail::assertQueued(ResetPasswordMail::class, 1);
    }
}
