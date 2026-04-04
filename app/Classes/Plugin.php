<?php

namespace App\Classes;

use App\Facades\Plugin as FacadesPlugin;
use App\Interfaces\HasPlugin;
use Filament\Panel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Translation\Translator;
use Rahmanramsi\LivewirePageGroup\PageGroup;
use Symfony\Component\Yaml\Yaml;

abstract class Plugin implements HasPlugin
{
    protected array $info = [];

    protected string $pluginPath;

    protected bool $isBooted = false;

    public function boot()
    {
        // Implement this method to run your plugin
    }

    public function bootPlugin()
    {
        View::addNamespace($this->getInfo('folder'), $this->getPluginPath('resources/views'));

        $this->boot();

        $this->isBooted = true;

        return $this;
    }

    public function load(): static
    {
        $this->loadTranslation();

        $this->info = $this->loadInformation();

        return $this;
    }

    public function getInfo(?string $key = null)
    {
        if ($key) {
            return $this->info[$key] ?? null;
        }

        return $this->info;
    }

    public function canBeDisabled(): bool
    {
        return true;
    }

    public function canBeEnabled(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->getSetting('enabled', false);
    }

    public function isDisabled(): bool
    {
        return ! $this->isEnabled();
    }

    public function enable($enable = true): void
    {
        $this->updateSetting('enabled', $enable);
    }

    public function disable(): void
    {
        $this->enable(false);
    }

    /**
     * Determine if a plugin is hidden from the admin panel
     */
    public function isHidden()
    {
        return false;
    }

    public function getPluginPath(?string $path = null)
    {
        return $this->pluginPath.($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    public function loadInformation()
    {
        return Yaml::parseFile($this->getPluginPath('index.yaml'));
    }

    public function setPluginPath($path): static
    {
        $this->pluginPath = $path;

        return $this;
    }

    public function getSetting($key, $default = null): mixed
    {
        return FacadesPlugin::getSetting($this, $key, $default);
    }

    public function updateSetting($key, $value): mixed
    {
        return FacadesPlugin::updateSetting($this, $key, $value);
    }

    public function onPanel(Panel $panel): void
    {
        // Implement this method to add your plugin to panel
    }

    public function onFrontend(PageGroup $frontend): void
    {
        // Implement this method to add your plugin to frontend
    }

    public function getPluginPage(): ?string
    {
        return null;
    }

    /**
     * Create public assets directory path.
     */
    public function enablePublicAsset(): void
    {
        $pluginAssetPath = $this->getPluginPath('public');
        if (file_exists($pluginAssetPath)) {
            $publicPluginAssetPath = public_path($this->getAssetsPath());

            if (file_exists($publicPluginAssetPath) && ! is_link($publicPluginAssetPath)) {
                try {
                    File::deleteDirectory($publicPluginAssetPath);
                } catch (\Throwable $th) {
                    throw $th;
                    Log::warning('Failed to fix public plugin asset directory symlink: '.$publicPluginAssetPath.' (please remove if manually)');
                }
            }

            // Create target symlink public plugins assets directory if required
            if (! file_exists($publicPluginAssetPath)) {
                File::relativeLink($pluginAssetPath, rtrim($publicPluginAssetPath, '/'));
            }
        }
    }

    public function getAssetsPath(?string $path = null): string
    {
        return 'plugin/'.mb_strtolower($this->getInfo('folder')).($path ? '/'.$path : '');
    }

    /**
     * Get theme's asset url.
     */
    public function asset(string $asset, bool $absolute = true): string
    {
        return $this->url($asset, $absolute);
    }

    /**
     * Get theme asset url.
     */
    public function url(string $url, bool $absolute = true, $versioning = true): string
    {
        $url = trim($url, '/');

        // return external URLs unmodified
        if (URL::isValidUrl($url)) {
            return $url;
        }

        // Lookup asset in current's theme assets path
        $fullUrl = $this->getAssetsPath($url);

        // Add versioning to the asset URL
        if ($versioning) {
            $version = $this->getInfo('version') ?? '1.0.0';
            $fullUrl .= '?v='.$version;
        }

        return $absolute ? asset($fullUrl) : $fullUrl;
    }

    protected function loadTranslation(): void
    {
        $langPath = $this->getPluginPath('lang');
        $translator = app()->make(Translator::class);

        $translator->addNamespace($this->getInfo('folder'), $langPath);
    }

    public function getVersion(): string
    {
        return $this->getInfo('version') ?? '1.0.0';
    }
}
