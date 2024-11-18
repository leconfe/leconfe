<?php

namespace App\Managers;

use App\Classes\Plugin as ClassesPlugin;
use App\Events\PluginInstalled;
use App\Models\PluginSetting;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class PluginManager
{
    protected Collection $plugins;

    protected bool $isBooted = false;

    public function __construct()
    {
        $this->plugins = collect();
    }

    public function initialize()
    {
        // TODO Add support for plugin in console
        if (app()->runningInConsole()) {
            return;
        }

        if (! app()->isInstalled()) {
            return;
        }

        $disk = $this->getDisk();

        collect($disk->directories())
            ->filter(function ($pluginDir) use ($disk) {
                try {
                    if (Str::contains($pluginDir, ' ')) {
                        throw new Exception("Plugin folder name ({$pluginDir}) cannot contain spaces");
                    }

                    if (! $disk->exists($pluginDir.DIRECTORY_SEPARATOR.'index.yaml')) {
                        throw new Exception("Plugin ({$pluginDir}) is missing index.yaml file");
                    }

                    if (! $disk->exists($pluginDir.DIRECTORY_SEPARATOR.'index.php')) {
                        throw new Exception("Plugin ({$pluginDir}) is missing index.php file");
                    }
                } catch (\Throwable $th) {
                    return false;
                }

                return true;
            })
            ->each(function ($pluginPath) use ($disk) {
                $plugin = $this->initiatePlugin($disk->path($pluginPath));

                $this->register($pluginPath, $plugin, $this->getSetting($pluginPath, 'enabled', false));
            });

    }

    public function reinitialize()
    {
        $this->plugins = collect();
        $this->initialize();
    }

    public function getDisk(): FilesystemContract
    {
        return Storage::disk('plugins');
    }

    public function getTempDisk()
    {
        return Storage::disk('plugins-tmp');
    }

    public function getPluginFullPath($path)
    {
        return $this->getDisk()->path($path);
    }

    public function register(string $id, ClassesPlugin $plugin, bool $boot = false)
    {
        try {
            $plugin->load();

            if ($boot) {
                $plugin->bootPlugin();
            }

            $this->plugins->put($id, $plugin);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    protected function initiatePlugin(string $pluginPath): ?ClassesPlugin
    {
        try {
            $plugin = include $pluginPath.DIRECTORY_SEPARATOR.'index.php';

            $plugin->setPluginPath($pluginPath);
            if (! $plugin instanceof ClassesPlugin) {
                throw new Exception('Plugin must return an instance of '.ClassesPlugin::class);
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        return $plugin;
    }

    public function getPlugins(bool $onlyEnabled = true)
    {
        return $this->plugins->when($onlyEnabled, fn ($plugins) => $plugins->filter(fn ($plugin) => $plugin->isEnabled()));
    }

    public function getPlugin(?string $path, bool $onlyEnabled = false): ?ClassesPlugin
    {
        return $this->getPlugins($onlyEnabled)->get($path);
    }

    protected function getCacheKey($plugin, $key)
    {
        $conferenceId = App::getCurrentConferenceId();
        $scheduledConferenceId = App::getCurrentScheduledConferenceId();

        return md5(implode('_', [
            'plugin_setting',
            $conferenceId,
            $scheduledConferenceId,
            $plugin,
            $key,
        ]));
    }

    public function getSetting(string $plugin, mixed $key, $default = null): mixed
    {
        return Cache::rememberForever($this->getCacheKey($plugin, $key), function () use ($plugin, $key, $default) {
            $setting = PluginSetting::query()
                ->where('conference_id', App::getCurrentConferenceId())
                ->where('scheduled_conference_id', App::getCurrentScheduledConferenceId() ?? 0)
                ->where('plugin', $plugin)
                ->where('key', $key)
                ->first();

            return $setting ? $this->convertFromDB($setting->value, $setting->type) : $default;
        });
    }

    public function updateSetting(string $plugin, $key, $value): mixed
    {
        $conferenceId = App::getCurrentConferenceId();
        $scheduledConferenceId = App::getCurrentScheduledConferenceId() ?? 0;

        Cache::forget($this->getCacheKey($plugin, $key));

        $type = $this->getType($value);

        return PluginSetting::query()
            ->updateOrInsert(
                [
                    'plugin' => $plugin,
                    'conference_id' => $conferenceId,
                    'scheduled_conference_id' => $scheduledConferenceId,
                    'key' => $key,
                ],
                [
                    'value' => $this->convertToDB($value, $type, true),
                    'type' => $type,
                ],
            );
    }

    public function cleanTempPlugins()
    {
        $this->getTempDisk()->deleteDirectory('');
    }

    public function install(string $file)
    {
        $pluginTempDisk = $this->getTempDisk();

        if (! $folderName = $this->extractToTempPlugin($file)) {
            throw new Exception('Cannot extract the plugin, please check the zip file');
        }

        $this->validatePlugin($pluginTempDisk->path($folderName));

        $fileSystem = new Filesystem;
        $fileSystem->copyDirectory($pluginTempDisk->path($folderName), $this->getDisk()->path($folderName));
        $fileSystem->delete($pluginTempDisk->path($folderName));

        try {
            $plugin = $this->initiatePlugin($this->getDisk()->path($folderName), true);
        } catch (\Throwable $th) {
            $pluginTempDisk->deleteDirectory($folderName);

            throw $th;
        }

        PluginInstalled::dispatch($plugin);

        $this->reinitialize();

        return true;
    }

    public function validatePlugin(string $pluginPath)
    {
        if (! file_exists($pluginPath)) {
            throw new Exception("Plugin {$pluginPath} not found");
        }

        $pluginName = basename($pluginPath);

        if (Str::contains($pluginPath, ' ')) {
            throw new Exception("Plugin folder name ({$pluginName}) cannot contain spaces");
        }

        if (! file_exists($pluginPath.DIRECTORY_SEPARATOR.'index.yaml')) {
            throw new Exception("Plugin ({$pluginName}) is missing index.yaml file");
        }

        if (! file_exists($pluginPath.DIRECTORY_SEPARATOR.'index.php')) {
            throw new Exception("Plugin ({$pluginName}) is missing index.php file");
        }
    }

    protected function extractToTempPlugin(string $filePath): string
    {
        try {
            if (! class_exists('ZipArchive')) {
                throw new Exception('Please Install PHP Zip Extension');
            }

            if (! file_exists($filePath)) {
                throw new Exception("File {$filePath} not found");
            }

            if (pathinfo($filePath)['extension'] != 'zip') {
                throw new Exception('Plugin extension must be .zip');
            }

            $zip = new ZipArchive;
            if ($zip->open($filePath) !== true) {
                throw new Exception('Cannot open the zip, please check the zip file');
            }

            $pluginInfo = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (! Str::contains($filename, 'index.yaml')) {
                    continue;
                }

                $pluginInfo = Yaml::parse($zip->getFromIndex($i));
            }

            if (! $pluginInfo) {
                throw new Exception('Plugin does not contain index.yaml file');
            }

            if (! isset($pluginInfo['name'])) {
                throw new Exception('Plugin must contain a name information in the index.yaml file');
            }

            if (! isset($pluginInfo['folder'])) {
                throw new Exception('Plugin must contain a `folder` information with the same name as the plugin folder name');
            }

            if (! $zip->extractTo($this->getTempDisk()->path(''))) {
                throw new Exception('Cannot extract the zip, please check the zip file');
            }

            $zip->close();

            if (! file_exists($this->getTempDisk()->path($pluginInfo['folder']))) {
                throw new Exception('Plugin must contain a folder with the same name as the plugin folder name');
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        return $pluginInfo['folder'];
    }

    public function uninstall(string $pluginPath): void
    {
        // Delete the plugin after response is sent
        app()->terminating(fn () => $this->getDisk()->deleteDirectory($pluginPath));
    }

    /**
     * Convert a PHP variable into a string to be stored in the DB
     *
     * @param  string  $type
     * @param  bool  $nullable  True iff the value is allowed to be null.
     * @return string
     */
    public function convertToDB($value, &$type, $nullable = false)
    {
        if ($nullable && $value === null) {
            return null;
        }

        if ($type == null) {
            $type = $this->getType($value);
        }

        switch ($type) {
            case 'object':
            case 'array':
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
            case 'bool':
            case 'boolean':
                // Cast to boolean, ensuring that string
                // "false" evaluates to boolean false
                $value = ($value && $value !== 'false') ? 1 : 0;
                break;
            case 'int':
            case 'integer':
                $value = (int) $value;
                break;
            case 'float':
            case 'number':
                $value = (float) $value;
                break;
            case 'date':
                if ($value !== null) {
                    if (! is_numeric($value)) {
                        $value = strtotime($value);
                    }
                    $value = date('Y-m-d H:i:s', $value);
                }
                break;
            case 'string':
            default:
                // do nothing.
        }

        return $value;
    }

    /**
     * Convert a value from the database to a specific type
     *
     * @param  mixed  $value  Value from the database
     * @param  string  $type  Type from the database, eg `string`
     * @param  bool  $nullable  True iff the value is allowed to be null
     */
    public function convertFromDB($value, $type, $nullable = false)
    {
        if ($nullable && $value === null) {
            return null;
        }
        switch ($type) {
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'number':
                return (float) $value;
            case 'object':
            case 'array':
                $decodedValue = json_decode($value, true);

                return ! is_null($decodedValue) ? $decodedValue : [];
            case 'date':
                return strtotime($value);
            case 'string':
            default:
                // Nothing required.
                break;
        }

        return $value;
    }

    public function getType($value)
    {
        switch (gettype($value)) {
            case 'boolean':
            case 'bool':
                return 'bool';
            case 'integer':
            case 'int':
                return 'int';
            case 'double':
            case 'float':
                return 'float';
            case 'array':
            case 'object':
                return 'object';
            case 'string':
            default:
                return 'string';
        }
    }
}
