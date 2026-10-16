<?php

namespace Tests\Feature\Multilingual;

use App\Http\Middleware\ShareLocalizedContextData;
use App\Models\Version;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

/**
 * An installation upgraded from 1.5.1 has no `translate` table until the upgrade runs.
 * The upgrade page and its Livewire requests must keep working until then.
 */
class UpgradeWithoutTranslationStorageTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    public function test_localized_context_middleware_is_skipped_while_upgrading(): void
    {
        Config::set('app.installed', true);
        Version::query()->create([
            'product_name' => 'Leconfe',
            'product_folder' => 'leconfe',
            'version' => '0.0.1',
        ]);
        Schema::drop('translate');

        $this->assertTrue(app()->isUpgrading());

        $response = (new ShareLocalizedContextData)->handle(
            Request::create('/livewire/update', 'POST'),
            fn () => new Response('next'),
        );

        $this->assertSame('next', $response->getContent());
    }

    public function test_translated_values_fall_back_to_stored_values_without_the_translate_table(): void
    {
        $conference = $this->makeConference('Annual Conference');
        $conference->setMeta('about', '<p>About us</p>');
        app()->getSite()->setMeta('name', 'Leconfe Site');

        // A new request on an installation that has not run the upgrade yet.
        Schema::drop('translate');
        app()->forgetInstance('leconfe.multilingual.storage-ready');
        $this->assertFalse(ContentLocales::hasTranslationStorage());

        $this->assertSame('Leconfe Site', app()->getSite()->translated('name'));
        $this->assertSame('Annual Conference', $conference->fresh()->translated('name'));
        $this->assertSame('<p>About us</p>', $conference->fresh()->translated('about'));

        $conference->update(['name' => 'Renamed Conference']);
        $this->assertSame('Renamed Conference', $conference->fresh()->translated('name'));
    }
}
