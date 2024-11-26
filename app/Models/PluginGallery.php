<?php

namespace App\Models;

use App\Classes\Plugin as ClassesPlugin;
use App\Facades\Plugin;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sushi\Sushi;

class PluginGallery extends Model
{
    use Sushi;

    public static $cacheTtl = 1800;

    protected $schema = [
        'id' => 'integer',
        'name' => 'string',
        'folder' => 'string',
        'type' => 'string',
        'summary' => 'string',
        'description' => 'string',
        'homepage' => 'string',
        'releases' => 'string',
        'author' => 'string',
    ];

    protected $casts = [
        'releases' => 'array',
    ];

    public function getRows(): array
    {
        return Cache::remember('plugin_gallery', static::$cacheTtl, function () {
            return $this->fetch();
        });
    }

    public function fetch()
    {
        try {
            $response = Http::acceptJson()->get(app()->getApiUrl('plugins'));

            if ($response->failed()) {
                return [];
            }
        } catch (\Throwable $th) {
            Log::error($th);

            return [];
        }

        return collect($response->json())
            ->filter(function ($plugin) {
                // Check if plugin is compatible with the current leconfe version
                $releases = collect($plugin['releases']);
                $currentVersion = app()->getInstalledVersion();

                return $releases->contains(fn ($release) => collect($release['compatibility'])->contains(fn ($value) => Semver::satisfies($currentVersion, $value)));
            })
            ->map(
                fn ($plugin) => [
                    'id' => $plugin['id'],
                    'cover' => $plugin['cover'],
                    'name' => $plugin['name'],
                    'folder' => $plugin['folder'],
                    'type' => $plugin['type'],
                    'summary' => $plugin['summary'],
                    'description' => $plugin['description'],
                    'homepage' => $plugin['homepage'],
                    'releases' => json_encode($plugin['releases']),
                    'author' => $plugin['author'],
                ],
            )
            ->values()
            ->toArray();
    }

    public function isInstalled(): bool
    {
        return Plugin::getPlugin($this->folder) instanceof ClassesPlugin;
    }

    public function isUpgradable(): bool
    {
        $plugin = Plugin::getPlugin($this->folder);

        if (! $plugin instanceof ClassesPlugin) {
            return false;
        }

        $latestRelease = $this->getLatestCompatibleRelease();

        return $latestRelease && version_compare($plugin->getVersion(), $latestRelease['version'], '<');
    }

    public function getLatestCompatibleRelease(): Collection
    {
        $releases = collect($this->releases);
        $currentVersion = app()->getInstalledVersion();

        return collect($releases->first(fn ($release) => collect($release['compatibility'])->contains(fn ($value) => Semver::satisfies($currentVersion, $value))));
    }

    public function install()
    {
        $latestRelease = $this->getLatestCompatibleRelease();
        if (! $latestRelease) {
            return false;
        }

        $filename = uniqid().'.zip';

        try {
            if (! Plugin::getTempDisk()->put($filename, file_get_contents($latestRelease['download_url']))) {
                throw new \Exception('The file could not be written to disk');
            }

            Plugin::install(Plugin::getTempDisk()->path($filename));

        } catch (\Throwable $th) {
            throw $th;
        } finally {
            Plugin::getTempDisk()->delete($filename);
        }

        return true;
    }
}
