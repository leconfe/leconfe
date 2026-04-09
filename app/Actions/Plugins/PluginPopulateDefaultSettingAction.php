<?php

namespace App\Actions\Plugins;

use App\Facades\Plugin;
use App\Models\CommitteeRole;
use App\Models\Conference;
use App\Models\PluginSetting;
use App\Models\ScheduledConference;
use App\Models\Site;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class PluginPopulateDefaultSettingAction
{
	use AsAction;

	public function handle(Site | Conference | ScheduledConference $context): void
	{
		$disk = Plugin::getDisk();

		$contextString = $context->getContextString();

		foreach ($disk->directories() as $pluginDir) {
			if (Str::contains($pluginDir, ' ')) {
				continue;
			}

			if (! $disk->exists($pluginDir . DIRECTORY_SEPARATOR . 'index.yaml')) {
				continue;
			}

			if (! $disk->exists($pluginDir . DIRECTORY_SEPARATOR . 'index.php')) {
				continue;
			}

			$informations = Yaml::parseFile($disk->path($pluginDir . DIRECTORY_SEPARATOR . 'index.yaml'));
			$targets = Arr::get($informations, 'targets');

			if (filled($targets) && !in_array($contextString, $targets)) {
				continue;
			}

			$settings = Arr::get($informations, 'settings');
			if (blank($settings)) {
				continue;
			}

			if ($context instanceof ScheduledConference) {
				foreach ($settings as $key => $value) {
					PluginSetting::query()
						->updateOrInsert(
							[
								'plugin' => Arr::get($informations, 'folder'),
								'conference_id' => $context->conference_id,
								'scheduled_conference_id' => $context->getKey(),
								'key' => $key,
							],
							[
								'value' => Plugin::convertToDB($value, null, true),
								'type' => Plugin::getType($value),
							],
						);
				}
			}

			if ($context instanceof Conference) {
				foreach ($settings as $key => $value) {
					PluginSetting::query()
						->updateOrInsert(
							[
								'plugin' => Arr::get($informations, 'folder'),
								'conference_id' => $context->getKey(),
								'scheduled_conference_id' => 0,
								'key' => $key,
							],
							[
								'value' => Plugin::convertToDB($value, null, true),
								'type' => Plugin::getType($value),
							],
						);
				}
			}

			if ($context instanceof Site) {
				foreach ($settings as $key => $value) {
					PluginSetting::query()
						->updateOrInsert(
							[
								'plugin' => Arr::get($informations, 'folder'),
								'conference_id' => 0,
								'scheduled_conference_id' => 0,
								'key' => $key,
							],
							[
								'value' => Plugin::convertToDB($value, null, true),
								'type' => Plugin::getType($value),
							],
						);
				}
			}
		}
	}
}
