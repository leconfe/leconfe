<?php

namespace Tests\Feature;

use App\Frontend\Website\Pages\ResetPasswordConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Livewire\Drawer\Utils;
use Tests\TestCase;

class WebsiteResetPasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_reset_password_confirmation_uses_simple_panel_layout(): void
    {
        $this->withoutVite()
            ->get($this->makeResetPasswordConfirmationUrl())
            ->assertOk()
            ->assertSee('fi-simple-layout', false)
            ->assertSee('fi-simple-page', false)
            ->assertSee('fi-input', false)
            ->assertDontSee('website::layouts.main', false)
            ->assertDontSee('input input-sm', false)
            ->assertDontSee('btn btn-primary', false);
    }

    public function test_reset_password_confirmation_submit_still_updates_password(): void
    {
        $user = $this->makeResetPasswordUser();
        $response = $this->withoutVite()
            ->get($this->makeResetPasswordConfirmationUrl($user))
            ->assertOk();

        $snapshot = Utils::extractAttributeDataFromHtml($response->getContent(), 'wire:snapshot');

        $this->withHeader('X-Livewire', true)->postJson(app('livewire')->getUpdateUri(), [
            'components' => [
                [
                    'snapshot' => json_encode($snapshot),
                    'updates' => [
                        'password' => 'new-password-12345',
                        'password_confirmation' => 'new-password-12345',
                    ],
                    'calls' => [
                        [
                            'method' => 'submit',
                            'params' => [],
                            'path' => '',
                        ],
                    ],
                ],
            ],
        ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password-12345', $user->refresh()->password));
    }

    protected function makeResetPasswordConfirmationUrl(?User $user = null): string
    {
        $user ??= $this->makeResetPasswordUser();

        return URL::temporarySignedRoute(
            ResetPasswordConfirmation::getRouteName('website'),
            now()->addHour(),
            [
                'user' => $user->email,
                'hash' => $this->makeResetPasswordHash($user),
            ],
        );
    }

    protected function makeResetPasswordHash(User $user): string
    {
        return sha1($user->email.$user->password.$user->getMeta('last_login'));
    }

    protected function makeResetPasswordUser(): User
    {
        return User::factory()->create([
            'email' => 'reset-password-confirmation-'.str()->random(8).'@example.test',
            'password' => Hash::make('password12345'),
        ]);
    }
}
