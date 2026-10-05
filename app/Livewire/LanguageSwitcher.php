<?php

namespace App\Livewire;

use App\Facades\Setting;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public function switchLanguage($lang)
    {
        if (! array_key_exists($lang, config('app.locales')) ||
            ! in_array($lang, Setting::get('languages', ['en']), true)) {
            abort(404);
        }

        session()->put('locale', $lang);

        return redirect(request()->header('Referer'));
    }

    public function render()
    {
        $languageCodes = Setting::get('languages', ['en']);
        $supportedLanguages = config('app.locales');

        $languages = [];

        foreach ($languageCodes as $code) {
            if (! array_key_exists($code, $supportedLanguages)) {
                continue;
            }

            $languages[$code] = $supportedLanguages[$code];
        }

        return view('livewire.language-switcher', [
            'languages' => $languages,
        ]);
    }
}
