<?php

namespace App\Models\Concerns;

use App\Facades\Setting;
use Exception;
use Illuminate\Support\Facades\App;
use Plank\Metable\Metable;

trait LocalizedMetable
{
	use Metable;

	public function getLocalizedMeta(string $key, ?string $locale = null)
	{
		$data = $this->getMeta($key);
		if (!is_array($data)) {
			return $data;
		}

		if (isset($data[$key])) {
			return $data[$key];
		}

		$locales = $this->getPossibleLocales();

		foreach ($locales as $locale) {
			if (!empty($data[$locale])) {
				return $data[$locale];
			}
		}

		return null;
	}

	public function getPossibleLocales(?string $preferredLocale = null): array
	{
		return array_unique(
			array_filter([
				$preferredLocale ?? App::getLocale(),
				Setting::get('default_language')
			])
		);
	}
}
