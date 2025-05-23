<?php

namespace App\Frontend\Website\Pages;

use App\Facades\Setting;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class SetLocale extends Page
{
	public function __invoke()
	{
        $currentRoute = Route::getCurrentRoute();
		$lang = $currentRoute->parameter('lang');

		$supportedLanguages = Setting::get('languages', ['en']);
		if (in_array($lang, $supportedLanguages)) {
			session()->put('locale', $lang);
		}

		if($referer = request()->header('Referer')){
			return redirect($referer);
		}

		return redirect('/');
	}

	public static function routes(PageGroup $pageGroup): void
	{
		$slug = static::getSlug();
		Route::get("/{$slug}/{lang}", static::class)
			->middleware(static::getRouteMiddleware($pageGroup))
			->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
			->name((string) str($slug)->replace('/', '.'));
	}
}