<?php

namespace Tests\Feature;

use App\Facades\Setting;
use App\Http\Middleware\SetLocale;
use App\Livewire\LanguageSwitcher;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class LocaleAvailabilityTest extends TestCase
{
    public function test_removed_language_in_saved_settings_falls_back_to_english(): void
    {
        Config::set('app.installed', true);

        Setting::shouldReceive('get')
            ->with('languages', ['en'])
            ->andReturn(['en', 'kab']);
        Setting::shouldReceive('get')
            ->with('default_language', 'en')
            ->andReturn('kab');

        session()->put('locale', 'kab');

        (new SetLocale)->handle(request(), fn () => response('ok'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_supported_default_remains_active_when_the_session_language_was_removed(): void
    {
        Config::set('app.installed', true);

        Setting::shouldReceive('get')
            ->with('languages', ['en'])
            ->andReturn(['en', 'fr', 'kab']);
        Setting::shouldReceive('get')
            ->with('default_language', 'en')
            ->andReturn('fr');

        session()->put('locale', 'kab');

        (new SetLocale)->handle(request(), fn () => response('ok'));

        $this->assertSame('fr', app()->getLocale());
    }

    public function test_language_switcher_rejects_a_removed_language(): void
    {
        Setting::shouldReceive('get')
            ->with('languages', ['en'])
            ->andReturn(['en', 'kab']);

        $this->expectException(NotFoundHttpException::class);

        (new LanguageSwitcher)->switchLanguage('kab');
    }
}
